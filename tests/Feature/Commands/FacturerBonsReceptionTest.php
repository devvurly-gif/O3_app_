<?php

namespace Tests\Feature\Commands;

use App\Models\CashTransaction;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\StockMouvement;
use App\Models\ThirdPartner;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

class FacturerBonsReceptionTest extends TestCase
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

    private function receiptNote(float $amount, string $date): DocumentHeader
    {
        $br = DocumentHeader::factory()->create([
            'document_type'    => 'ReceiptNotePurchase',
            'document_title'   => 'Bon de Réception',
            'thirdPartner_id'  => $this->supplier->id,
            'user_id'          => $this->admin->id,
            'status'           => 'confirmed',
            'issued_at'        => $date,
        ]);

        DocumentLigne::create([
            'document_header_id' => $br->id,
            'sort_order'         => 1,
            'line_type'          => 'product',
            'designation'        => 'Marchandise',
            'quantity'           => 1,
            'unit_price'         => $amount,
            'tax_percent'        => 0,
        ]);

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

    // ── Facturation ───────────────────────────────────────────────

    public function test_it_invoices_every_receipt_note_keeping_its_date(): void
    {
        $br = $this->receiptNote(74790, '2026-07-09');

        $this->artisan('achats:facturer-br', ['--supplier' => 'LEADER STAR'])
             ->assertSuccessful();

        $invoice = DocumentHeader::where('document_type', 'InvoicePurchase')->firstOrFail();

        $this->assertSame($br->id, $invoice->parent_id);
        $this->assertSame('2026-07-09', $invoice->issued_at->toDateString());
        $this->assertSame(74790.0, (float) $invoice->footer->total_ttc);
        $this->assertSame(74790.0, (float) $invoice->footer->amount_due);
        $this->assertSame('received', $br->fresh()->status);
    }

    public function test_it_does_not_move_stock_again(): void
    {
        $this->receiptNote(1000, '2026-07-09');

        $this->artisan('achats:facturer-br')->assertSuccessful();

        // La marchandise est entrée à la réception ; la facturer ne la fait pas
        // entrer une seconde fois.
        $this->assertSame(0, StockMouvement::count());
    }

    public function test_it_skips_receipt_notes_already_invoiced(): void
    {
        $this->receiptNote(1000, '2026-07-09');

        $this->artisan('achats:facturer-br')->assertSuccessful();
        $this->artisan('achats:facturer-br')->assertSuccessful();

        $this->assertSame(1, DocumentHeader::where('document_type', 'InvoicePurchase')->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->receiptNote(1000, '2026-07-09');

        $this->artisan('achats:facturer-br', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, DocumentHeader::where('document_type', 'InvoicePurchase')->count());
    }

    // ── Règlement ─────────────────────────────────────────────────

    public function test_it_allocates_the_payment_oldest_invoice_first(): void
    {
        $this->receiptNote(74790, '2026-07-09');
        $this->receiptNote(4500, '2026-08-12');

        $this->artisan('achats:facturer-br', [
            '--supplier'     => 'LEADER STAR',
            '--payment'      => 40000,
            '--payment-date' => '2026-07-27',
        ])->assertSuccessful();

        $invoices = DocumentHeader::where('document_type', 'InvoicePurchase')
            ->with('footer')->orderBy('issued_at')->get();

        $this->assertSame(40000.0, (float) $invoices[0]->footer->amount_paid);
        $this->assertSame(34790.0, (float) $invoices[0]->footer->amount_due);
        $this->assertSame(0.0, (float) $invoices[1]->footer->amount_paid);
        $this->assertSame('2026-07-27', Payment::firstOrFail()->paid_at->toDateString());
    }

    public function test_the_regularisation_notifies_nobody(): void
    {
        $this->receiptNote(74790, '2026-07-09');

        // Le faux est posé apres la fixture : creer un BR notifie legitimement
        // le staff, ce qu'on ne cherche pas a mesurer ici.
        Notification::fake();

        $this->artisan('achats:facturer-br', [
            '--supplier' => 'LEADER STAR',
            '--payment'  => 40000,
        ])->assertSuccessful();

        // Ni mail au fournisseur pour un reglement vieux d'un mois, ni huit
        // notifications "nouvelle facture" pour des documents antidates.
        Notification::assertNothingSent();
    }

    public function test_the_remaining_debt_shows_on_the_supplier_account(): void
    {
        $this->receiptNote(74790, '2026-07-09');
        $this->receiptNote(28665, '2026-08-12');

        $this->artisan('achats:facturer-br', [
            '--supplier' => 'LEADER STAR',
            '--payment'  => 40000,
        ])->assertSuccessful();

        // 103 455 facturés − 40 000 réglés.
        $this->assertSame(63455.0, (float) $this->supplier->fresh()->encours_actuel);
    }

    // ── Apport ────────────────────────────────────────────────────

    public function test_apport_offsets_the_payment_so_the_till_does_not_move(): void
    {
        $this->receiptNote(74790, '2026-07-09');

        $this->artisan('achats:facturer-br', [
            '--supplier'     => 'LEADER STAR',
            '--payment'      => 40000,
            '--payment-date' => '2026-07-27',
            '--apport'       => true,
        ])->assertSuccessful();

        $apport = CashTransaction::firstOrFail();
        $this->assertSame('in', $apport->ct_direction);
        $this->assertSame(40000.0, (float) $apport->ct_amount);
        $this->assertSame('2026-07-27', $apport->ct_date->toDateString());
        $this->assertSame('Apport', $apport->category?->cc_title);

        // Le règlement sort, l'apport entre : la trésorerie ne bouge pas.
        $summary = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/treasury/summary')->assertOk()->json();

        $this->assertSame(40000.0, (float) $summary['total_in']);
        $this->assertSame(40000.0, (float) $summary['total_out']);
        $this->assertSame(0.0, (float) $summary['net']);
        $this->assertSame(0.0, (float) $summary['total_balance']);
    }

    public function test_without_apport_the_till_carries_the_payment(): void
    {
        $this->receiptNote(74790, '2026-07-09');

        $this->artisan('achats:facturer-br', [
            '--supplier' => 'LEADER STAR',
            '--payment'  => 40000,
        ])->assertSuccessful();

        $summary = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/treasury/summary')->assertOk()->json();

        $this->assertSame(-40000.0, (float) $summary['net']);
    }

    public function test_the_payment_reaches_the_treasury_journal(): void
    {
        $this->receiptNote(74790, '2026-07-09');

        $this->artisan('achats:facturer-br', [
            '--supplier'     => 'LEADER STAR',
            '--payment'      => 40000,
            '--payment-date' => '2026-07-27',
            '--apport'       => true,
        ])->assertSuccessful();

        $journal = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/treasury/journal')->assertOk()->json('data');

        $payment = collect($journal)->firstWhere('source', 'payment');

        $this->assertNotNull($payment);
        $this->assertSame('out', $payment['direction']);
        $this->assertSame('Caisse espèces', $payment['account_title']);
    }
}
