<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\Role;
use App\Models\StockMouvement;
use App\Models\ThirdPartner;
use App\Models\User;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Facturation groupée depuis la liste des achats : on coche des bons de
 * réception, on obtient une facture unique.
 */
class GroupReceiptNotesTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->supplier = ThirdPartner::factory()->create([
            'tp_title' => 'LEADER STAR',
            'tp_Role'  => 'supplier',
        ]);

        DocumentIncrementor::factory()->create([
            'di_model'  => 'InvoicePurchase',
            'di_title'  => 'Facture Achat',
            'template'  => 'FA-{YYYY}-{NNNN}',
            'nextTrick' => 1,
        ]);
    }

    private function receipt(float $amount, string $date, ?ThirdPartner $supplier = null, int $lines = 2): DocumentHeader
    {
        $br = DocumentHeader::factory()->create([
            'document_type'   => 'ReceiptNotePurchase',
            'thirdPartner_id' => ($supplier ?? $this->supplier)->id,
            'user_id'         => $this->admin->id,
            'status'          => 'confirmed',
            'issued_at'       => $date,
        ]);

        for ($i = 1; $i <= $lines; $i++) {
            DocumentLigne::create([
                'document_header_id' => $br->id,
                'sort_order'         => $i,
                'line_type'          => 'product',
                'designation'        => 'Article ' . $i,
                'quantity'           => 1,
                'unit_price'         => $amount / $lines,
                'tax_percent'        => 0,
            ]);
        }

        DocumentFooter::factory()->create([
            'document_header_id' => $br->id,
            'total_ht'           => $amount,
            'total_tax'          => 0,
            'total_ttc'          => $amount,
            'amount_paid'        => 0,
            'amount_due'         => $amount,
        ]);

        return $br;
    }

    private function group(array $ids, array $extra = [])
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/achats/documents/regrouper-bons', array_merge([
                'receipt_ids' => $ids,
            ], $extra));
    }

    // ── Cas nominal ───────────────────────────────────────────────

    public function test_it_bills_several_receipt_notes_as_one_invoice(): void
    {
        $a = $this->receipt(74790, '2026-07-09', lines: 3);
        $b = $this->receipt(28665, '2026-08-24', lines: 2);

        $response = $this->group([$a->id, $b->id], ['issued_at' => '2026-08-24'])
            ->assertCreated();

        $invoice = DocumentHeader::where('document_type', 'InvoicePurchase')->firstOrFail();

        $this->assertSame(103455.0, (float) $invoice->footer->total_ttc);
        $this->assertSame(103455.0, (float) $invoice->footer->amount_due);
        $this->assertSame(5, $invoice->lignes()->count());
        $this->assertSame('2026-08-24', $invoice->issued_at->toDateString());
        $this->assertStringStartsWith('FA-', $invoice->reference);
        $this->assertStringContainsString($invoice->reference, $response->json('message'));
    }

    public function test_the_receipt_notes_survive_but_leave_the_billable_list(): void
    {
        $a = $this->receipt(1000, '2026-07-09');
        $b = $this->receipt(2000, '2026-07-10');

        $this->group([$a->id, $b->id])->assertCreated();

        // Le bon reste : c'est la preuve de la livraison.
        $this->assertSame('converted', $a->fresh()->status);
        $this->assertSame('converted', $b->fresh()->status);
        $this->assertNotNull(DocumentHeader::find($a->id));
    }

    public function test_it_does_not_touch_stock(): void
    {
        $a = $this->receipt(1000, '2026-07-09');
        $b = $this->receipt(2000, '2026-07-10');

        $this->group([$a->id, $b->id])->assertCreated();

        // La marchandise est entrée à la réception, pas à la facturation.
        $this->assertSame(0, StockMouvement::count());
    }

    public function test_the_supplier_debt_reflects_the_grouped_invoice(): void
    {
        $a = $this->receipt(74790, '2026-07-09');
        $b = $this->receipt(28665, '2026-08-24');

        $this->group([$a->id, $b->id])->assertCreated();

        $this->assertSame(103455.0, (float) $this->supplier->fresh()->encours_actuel);
    }

    public function test_a_single_receipt_can_be_billed_too(): void
    {
        $a = $this->receipt(840, '2026-08-24');

        $this->group([$a->id])->assertCreated();

        $this->assertSame(840.0, (float) DocumentHeader::where('document_type', 'InvoicePurchase')
            ->firstOrFail()->footer->total_ttc);
    }

    // ── Garde-fous ────────────────────────────────────────────────

    public function test_it_refuses_receipts_from_two_different_suppliers(): void
    {
        $other = ThirdPartner::factory()->create(['tp_title' => 'AQUA REDA', 'tp_Role' => 'supplier']);

        $a = $this->receipt(1000, '2026-07-09');
        $b = $this->receipt(2000, '2026-07-10', $other);

        $this->group([$a->id, $b->id])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Tous les documents doivent appartenir au même fournisseur.']);

        $this->assertSame(0, DocumentHeader::where('document_type', 'InvoicePurchase')->count());
    }

    public function test_it_refuses_an_already_billed_receipt(): void
    {
        $a = $this->receipt(1000, '2026-07-09');
        $b = $this->receipt(2000, '2026-07-10');

        $this->group([$a->id, $b->id])->assertCreated();

        // Le bon a bien un enfant facture : le recocher doit echouer.
        $this->group([$a->id, $b->id])->assertStatus(422);

        $this->assertSame(1, DocumentHeader::where('document_type', 'InvoicePurchase')->count());
    }

    public function test_it_refuses_a_draft_receipt(): void
    {
        $a = $this->receipt(1000, '2026-07-09');
        $b = $this->receipt(2000, '2026-07-10');
        $b->update(['status' => 'draft']);

        $this->group([$a->id, $b->id])->assertStatus(422);
    }

    public function test_it_refuses_documents_that_are_not_receipt_notes(): void
    {
        $a = $this->receipt(1000, '2026-07-09');
        $invoice = DocumentHeader::factory()->invoice()->create([
            'user_id'         => $this->admin->id,
            'thirdPartner_id' => $this->supplier->id,
        ]);

        $this->group([$a->id, $invoice->id])->assertStatus(422);
    }

    public function test_an_empty_selection_is_rejected(): void
    {
        $this->group([])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['receipt_ids']);
    }

    // ── Accès ─────────────────────────────────────────────────────

    public function test_the_warehouse_role_cannot_bill(): void
    {
        $magasinier = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'warehouse'], ['display_name' => 'Magasinier'])->id,
        ]);

        $a = $this->receipt(1000, '2026-07-09');
        $b = $this->receipt(2000, '2026-07-10');

        $this->actingAs($magasinier, 'sanctum')
            ->postJson('/api/achats/documents/regrouper-bons', ['receipt_ids' => [$a->id, $b->id]])
            ->assertForbidden();
    }
}
