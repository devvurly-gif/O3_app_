<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Setting;
use App\Models\StockMouvement;
use App\Models\ThirdPartner;
use App\Models\User;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Facturation groupée côté ventes : on coche des bons de livraison, on obtient
 * une facture unique.
 */
class GroupDeliveryNotesTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->customer = ThirdPartner::factory()->create([
            'tp_title' => 'MR JAWAD LIGHT',
            'tp_Role'  => 'customer',
        ]);

        DocumentIncrementor::factory()->create([
            'di_model'  => 'InvoiceSale',
            'di_title'  => 'Facture Vente',
            'template'  => 'FV-{YYYY}-{NNNN}',
            'nextTrick' => 1,
        ]);
    }

    private function deliveryNote(float $amount, string $date, ?ThirdPartner $partner = null, int $lines = 2): DocumentHeader
    {
        $bl = DocumentHeader::factory()->create([
            'document_type'   => 'DeliveryNote',
            'thirdPartner_id' => ($partner ?? $this->customer)->id,
            'user_id'         => $this->admin->id,
            'status'          => 'confirmed',
            'issued_at'       => $date,
        ]);

        for ($i = 1; $i <= $lines; $i++) {
            DocumentLigne::create([
                'document_header_id' => $bl->id,
                'sort_order'         => $i,
                'line_type'          => 'product',
                'designation'        => 'Article ' . $i,
                'quantity'           => 1,
                'unit_price'         => $amount / $lines,
                'tax_percent'        => 0,
            ]);
        }

        DocumentFooter::factory()->create([
            'document_header_id' => $bl->id,
            'total_ht'           => $amount,
            'total_tax'          => 0,
            'total_ttc'          => $amount,
            'amount_paid'        => 0,
            'amount_due'         => $amount,
        ]);

        return $bl;
    }

    private function group(array $ids, array $extra = [])
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/ventes/documents/regrouper-bls', array_merge([
                'delivery_note_ids' => $ids,
            ], $extra));
    }

    private function invoice(): DocumentHeader
    {
        return DocumentHeader::where('document_type', 'InvoiceSale')->firstOrFail();
    }

    // ── Cas nominal ───────────────────────────────────────────────

    public function test_it_bills_several_delivery_notes_as_one_invoice(): void
    {
        $a = $this->deliveryNote(12000, '2026-08-03', lines: 3);
        $b = $this->deliveryNote(3500, '2026-08-19', lines: 2);

        $this->group([$a->id, $b->id], ['issued_at' => '2026-08-31'])->assertCreated();

        $invoice = $this->invoice();

        $this->assertSame(15500.0, (float) $invoice->footer->total_ttc);
        $this->assertSame(5, $invoice->lignes()->count());
        $this->assertSame('2026-08-31', $invoice->issued_at->toDateString());
        $this->assertStringStartsWith('FV-', $invoice->reference);
    }

    public function test_the_delivery_notes_survive_and_are_marked_billed(): void
    {
        $a = $this->deliveryNote(1000, '2026-08-03');
        $b = $this->deliveryNote(2000, '2026-08-04');

        $this->group([$a->id, $b->id])->assertCreated();

        // 'converted' et non 'delivered' : isBilled() ne reconnait que le
        // premier, et un BL 'delivered' sans facture enfant resterait
        // refacturable un par un par-dessus la facture groupee.
        $this->assertSame('converted', $a->fresh()->status);
        $this->assertTrue($a->fresh()->isBilled());
        $this->assertNotNull(DocumentHeader::find($a->id));
    }

    public function test_it_does_not_move_stock_again(): void
    {
        $a = $this->deliveryNote(1000, '2026-08-03');
        $b = $this->deliveryNote(2000, '2026-08-04');

        $this->group([$a->id, $b->id])->assertCreated();

        // La marchandise est sortie a la livraison, pas a la facturation.
        $this->assertSame(0, StockMouvement::count());
    }

    /**
     * Un BL non facturé compte déjà dans l'encours du client : la facture prend
     * le relais, le total ne doit pas bouger d'un dirham.
     */
    public function test_the_customer_balance_does_not_move(): void
    {
        Setting::updateOrCreate(
            ['st_domain' => 'ventes', 'st_key' => 'paiement_sur_bl'],
            ['st_value' => 'true']
        );

        $a = $this->deliveryNote(12000, '2026-08-03');
        $b = $this->deliveryNote(3500, '2026-08-19');

        $this->customer->recalculateEncours();
        $before = (float) $this->customer->fresh()->encours_actuel;

        $this->group([$a->id, $b->id])->assertCreated();

        $this->assertSame(15500.0, $before);
        $this->assertSame($before, (float) $this->customer->fresh()->encours_actuel);
    }

    /**
     * Avec « paiement sur BL » actif, un bon peut déjà porter un règlement.
     */
    public function test_payments_recorded_on_a_delivery_note_follow_the_invoice(): void
    {
        $a = $this->deliveryNote(12000, '2026-08-03');
        $b = $this->deliveryNote(3500, '2026-08-19');

        Payment::factory()->create([
            'document_header_id' => $a->id,
            'amount'             => 5000,
            'method'             => 'cash',
            'paid_at'            => '2026-08-05',
            'user_id'            => $this->admin->id,
        ]);

        $this->group([$a->id, $b->id])->assertCreated();

        $invoice = $this->invoice();

        $this->assertSame($invoice->id, Payment::firstOrFail()->document_header_id);
        $this->assertSame(5000.0, (float) $invoice->footer->amount_paid);
        $this->assertSame(10500.0, (float) $invoice->footer->amount_due);
        $this->assertSame('partial', $invoice->fresh()->status);
    }

    // ── Garde-fous ────────────────────────────────────────────────

    public function test_it_refuses_notes_from_two_different_customers(): void
    {
        $other = ThirdPartner::factory()->create(['tp_title' => 'Hafud', 'tp_Role' => 'customer']);

        $a = $this->deliveryNote(1000, '2026-08-03');
        $b = $this->deliveryNote(2000, '2026-08-04', $other);

        $this->group([$a->id, $b->id])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Tous les documents doivent appartenir au même fournisseur.']);

        $this->assertSame(0, DocumentHeader::where('document_type', 'InvoiceSale')->count());
    }

    public function test_it_refuses_an_already_billed_note(): void
    {
        $a = $this->deliveryNote(1000, '2026-08-03');
        $b = $this->deliveryNote(2000, '2026-08-04');

        $this->group([$a->id, $b->id])->assertCreated();
        $this->group([$a->id, $b->id])->assertStatus(422);

        $this->assertSame(1, DocumentHeader::where('document_type', 'InvoiceSale')->count());
    }

    public function test_it_refuses_a_note_already_converted_one_by_one(): void
    {
        $a = $this->deliveryNote(1000, '2026-08-03');
        $b = $this->deliveryNote(2000, '2026-08-04');

        // Conversion unitaire par l'ecran habituel.
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/ventes/documents/{$a->id}/confirmer", ['payment_method' => 'credit'])
            ->assertCreated();

        $this->group([$a->id, $b->id])->assertStatus(422);
    }

    public function test_it_refuses_a_draft_note(): void
    {
        $a = $this->deliveryNote(1000, '2026-08-03');
        $b = $this->deliveryNote(2000, '2026-08-04');
        $b->update(['status' => 'draft']);

        $this->group([$a->id, $b->id])->assertStatus(422);
    }

    public function test_it_refuses_documents_that_are_not_delivery_notes(): void
    {
        $a = $this->deliveryNote(1000, '2026-08-03');
        $quote = DocumentHeader::factory()->create([
            'document_type'   => 'QuoteSale',
            'thirdPartner_id' => $this->customer->id,
            'user_id'         => $this->admin->id,
        ]);

        $this->group([$a->id, $quote->id])->assertStatus(422);
    }

    public function test_an_empty_selection_is_rejected(): void
    {
        $this->group([])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['delivery_note_ids']);
    }

    // ── Accès ─────────────────────────────────────────────────────

    public function test_the_warehouse_role_cannot_bill(): void
    {
        $magasinier = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'warehouse'], ['display_name' => 'Magasinier'])->id,
        ]);

        $a = $this->deliveryNote(1000, '2026-08-03');
        $b = $this->deliveryNote(2000, '2026-08-04');

        $this->actingAs($magasinier, 'sanctum')
            ->postJson('/api/ventes/documents/regrouper-bls', ['delivery_note_ids' => [$a->id, $b->id]])
            ->assertForbidden();
    }
}
