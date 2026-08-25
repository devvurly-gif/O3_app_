<?php

namespace Tests\Feature\Commands;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\ThirdPartner;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

class RegrouperFacturesAchatTest extends TestCase
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

    /** Un BR facture : le couple bon de reception + facture, comme en production. */
    private function invoicedReceipt(float $amount, string $date, int $lines = 2): DocumentHeader
    {
        $br = DocumentHeader::factory()->create([
            'document_type'   => 'ReceiptNotePurchase',
            'thirdPartner_id' => $this->supplier->id,
            'user_id'         => $this->admin->id,
            'status'          => 'received',
            'issued_at'       => $date,
        ]);

        $invoice = DocumentHeader::factory()->create([
            'document_type'   => 'InvoicePurchase',
            'parent_id'       => $br->id,
            'thirdPartner_id' => $this->supplier->id,
            'user_id'         => $this->admin->id,
            'status'          => 'pending',
            'issued_at'       => $date,
        ]);

        for ($i = 1; $i <= $lines; $i++) {
            DocumentLigne::create([
                'document_header_id' => $invoice->id,
                'sort_order'         => $i,
                'line_type'          => 'product',
                'designation'        => 'Article ' . $i,
                'quantity'           => 1,
                'unit_price'         => $amount / $lines,
                'tax_percent'        => 0,
            ]);
        }

        DocumentFooter::factory()->create([
            'document_header_id' => $invoice->id,
            'total_ht'           => $amount,
            'total_tax'          => 0,
            'total_ttc'          => $amount,
            'amount_paid'        => 0,
            'amount_due'         => $amount,
        ]);

        return $invoice;
    }

    private function grouped(): DocumentHeader
    {
        return DocumentHeader::where('document_title', 'Facture Achat groupée')->firstOrFail();
    }

    // ── Fusion ────────────────────────────────────────────────────

    public function test_it_merges_totals_and_lines_into_one_invoice(): void
    {
        $this->invoicedReceipt(74790, '2026-07-09', 3);
        $this->invoicedReceipt(28665, '2026-08-24', 2);

        $this->artisan('achats:regrouper-factures', [
            '--supplier' => 'LEADER STAR',
            '--date'     => '2026-08-24',
        ])->assertSuccessful();

        $grouped = $this->grouped();

        $this->assertSame('2026-08-24', $grouped->issued_at->toDateString());
        $this->assertSame(103455.0, (float) $grouped->footer->total_ttc);
        $this->assertSame(5, $grouped->lignes()->count());
        $this->assertStringContainsString('Facture groupée — BR :', $grouped->notes);
    }

    public function test_the_replaced_invoices_leave_the_listings(): void
    {
        $this->invoicedReceipt(74790, '2026-07-09');
        $this->invoicedReceipt(28665, '2026-08-24');

        $this->artisan('achats:regrouper-factures', ['--supplier' => 'LEADER STAR'])
             ->assertSuccessful();

        // Une seule facture visible, les deux sources sont en suppression douce.
        $this->assertSame(1, DocumentHeader::where('document_type', 'InvoicePurchase')->count());
        $this->assertSame(3, DocumentHeader::withTrashed()->where('document_type', 'InvoicePurchase')->count());
    }

    public function test_payments_follow_the_new_invoice(): void
    {
        $invoice = $this->invoicedReceipt(74790, '2026-07-09');
        $this->invoicedReceipt(28665, '2026-08-24');

        Payment::factory()->create([
            'document_header_id' => $invoice->id,
            'amount'             => 40000,
            'method'             => 'cash',
            'paid_at'            => '2026-07-27',
            'user_id'            => $this->admin->id,
        ]);

        $this->artisan('achats:regrouper-factures', ['--supplier' => 'LEADER STAR'])
             ->assertSuccessful();

        $grouped = $this->grouped();

        $this->assertSame($grouped->id, Payment::firstOrFail()->document_header_id);
        $this->assertSame(40000.0, (float) $grouped->footer->amount_paid);
        $this->assertSame(63455.0, (float) $grouped->footer->amount_due);
        $this->assertSame('partial', $grouped->fresh()->status);
    }

    public function test_the_supplier_balance_does_not_move(): void
    {
        $invoice = $this->invoicedReceipt(74790, '2026-07-09');
        $this->invoicedReceipt(28665, '2026-08-24');

        Payment::factory()->create([
            'document_header_id' => $invoice->id,
            'amount'             => 40000,
            'method'             => 'cash',
            'user_id'            => $this->admin->id,
        ]);

        $this->supplier->recalculateEncours();
        $before = (float) $this->supplier->fresh()->encours_actuel;

        $this->artisan('achats:regrouper-factures', ['--supplier' => 'LEADER STAR'])
             ->assertSuccessful();

        // Regrouper ne cree ni n'efface de dette : 103 455 - 40 000.
        $this->assertSame(63455.0, $before);
        $this->assertSame($before, (float) $this->supplier->fresh()->encours_actuel);
    }

    // ── Garde-fous ────────────────────────────────────────────────

    public function test_the_receipt_notes_cannot_be_invoiced_twice(): void
    {
        $this->invoicedReceipt(74790, '2026-07-09');
        $this->invoicedReceipt(28665, '2026-08-24');

        $this->artisan('achats:regrouper-factures', ['--supplier' => 'LEADER STAR'])
             ->assertSuccessful();

        // Les BR sont marques 'converted' : sans ca, facturer-br les verrait
        // sans facture — leur facture ayant ete supprimee — et refacturerait
        // par-dessus la facture groupee.
        $this->artisan('achats:facturer-br', ['--supplier' => 'LEADER STAR'])
             ->assertSuccessful();

        $this->assertSame(1, DocumentHeader::where('document_type', 'InvoicePurchase')->count());
    }

    public function test_it_refuses_to_group_a_single_invoice(): void
    {
        $this->invoicedReceipt(74790, '2026-07-09');

        $this->artisan('achats:regrouper-factures', ['--supplier' => 'LEADER STAR'])
             ->assertFailed();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->invoicedReceipt(74790, '2026-07-09');
        $this->invoicedReceipt(28665, '2026-08-24');

        $this->artisan('achats:regrouper-factures', [
            '--supplier' => 'LEADER STAR',
            '--dry-run'  => true,
        ])->assertSuccessful();

        $this->assertSame(2, DocumentHeader::where('document_type', 'InvoicePurchase')->count());
    }

    public function test_it_notifies_nobody(): void
    {
        $this->invoicedReceipt(74790, '2026-07-09');
        $this->invoicedReceipt(28665, '2026-08-24');

        Notification::fake();

        $this->artisan('achats:regrouper-factures', ['--supplier' => 'LEADER STAR'])
             ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_the_supplier_invoice_number_is_kept_in_the_notes(): void
    {
        $this->invoicedReceipt(74790, '2026-07-09');
        $this->invoicedReceipt(28665, '2026-08-24');

        $this->artisan('achats:regrouper-factures', [
            '--supplier'     => 'LEADER STAR',
            '--supplier-ref' => 'FAC-2026-993',
        ])->assertSuccessful();

        $this->assertStringContainsString('FAC-2026-993', $this->grouped()->notes);
    }
}
