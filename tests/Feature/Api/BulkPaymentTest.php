<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the bulk-payment allocation on a partner's unpaid documents.
 *
 *   POST /api/third-partners/{id}/bulk-payment
 *     - distributes the amount FIFO (oldest issued_at first)
 *     - if `document_ids` is supplied, restricts allocation to those docs
 *     - includes BL only when setting `ventes.paiement_sur_bl` == 'true'
 *     - never over-applies: extra amount is left unallocated (no negative due)
 *     - a fully applied document flips to 'paid', a partially applied one
 *       to 'partial'
 */
class BulkPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ThirdPartner $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin    = User::factory()->admin()->create();
        $this->customer = ThirdPartner::factory()->customer()->create(['encours_actuel' => 0]);
    }

    private function makeInvoice(float $total, string $issuedAt): DocumentHeader
    {
        $doc = DocumentHeader::factory()->invoice()->create([
            'user_id'         => $this->admin->id,
            'status'          => 'confirmed',
            'thirdPartner_id' => $this->customer->id,
            'issued_at'       => $issuedAt,
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ttc'          => $total,
            'amount_due'         => $total,
            'amount_paid'        => 0,
        ]);
        return $doc;
    }

    private function makeDeliveryNote(float $total, string $issuedAt): DocumentHeader
    {
        $doc = DocumentHeader::factory()->deliveryNote()->create([
            'user_id'         => $this->admin->id,
            'status'          => 'confirmed',
            'thirdPartner_id' => $this->customer->id,
            'issued_at'       => $issuedAt,
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ttc'          => $total,
            'amount_due'         => $total,
            'amount_paid'        => 0,
        ]);
        return $doc;
    }

    private function bulkPay(array $payload)
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/third-partners/{$this->customer->id}/bulk-payment", $payload);
    }

    public function test_bulk_payment_allocates_fifo_oldest_first(): void
    {
        $oldest = $this->makeInvoice(500, '2025-01-01');
        $middle = $this->makeInvoice(500, '2025-02-01');
        $newest = $this->makeInvoice(500, '2025-03-01');

        $this->bulkPay([
            'amount'  => 800,
            'method'  => 'cash',
            'paid_at' => now()->format('Y-m-d'),
        ])->assertOk();

        // Oldest fully paid (500), middle partial (300/500), newest untouched.
        $this->assertEquals(0,   (float) $oldest->fresh()->footer->amount_due);
        $this->assertEquals(200, (float) $middle->fresh()->footer->amount_due);
        $this->assertEquals(500, (float) $newest->fresh()->footer->amount_due);

        $this->assertSame('paid',      $oldest->fresh()->status);
        $this->assertSame('partial',   $middle->fresh()->status);
        $this->assertSame('confirmed', $newest->fresh()->status);
    }

    public function test_bulk_payment_respects_document_ids_restriction(): void
    {
        $oldest = $this->makeInvoice(500, '2025-01-01');
        $target = $this->makeInvoice(500, '2025-02-01');
        $other  = $this->makeInvoice(500, '2025-03-01');

        // Pay only the middle invoice, skipping the older one even though
        // FIFO would normally pick it first.
        $this->bulkPay([
            'amount'       => 500,
            'method'       => 'bank_transfer',
            'paid_at'      => now()->format('Y-m-d'),
            'document_ids' => [$target->id],
        ])->assertOk();

        $this->assertEquals(500, (float) $oldest->fresh()->footer->amount_due, 'Oldest must be untouched when not in document_ids.');
        $this->assertEquals(0,   (float) $target->fresh()->footer->amount_due);
        $this->assertEquals(500, (float) $other->fresh()->footer->amount_due);

        $this->assertSame('paid', $target->fresh()->status);
    }

    public function test_bulk_payment_ignores_bl_when_paiement_sur_bl_disabled(): void
    {
        Setting::set('ventes', 'paiement_sur_bl', 'false');

        $bl      = $this->makeDeliveryNote(500, '2025-01-01');
        $invoice = $this->makeInvoice(500, '2025-02-01');

        $this->bulkPay([
            'amount'  => 500,
            'method'  => 'cash',
            'paid_at' => now()->format('Y-m-d'),
        ])->assertOk();

        // BL skipped → the whole 500 flows to the invoice.
        $this->assertEquals(500, (float) $bl->fresh()->footer->amount_due);
        $this->assertEquals(0,   (float) $invoice->fresh()->footer->amount_due);
    }

    public function test_bulk_payment_includes_bl_when_paiement_sur_bl_enabled(): void
    {
        Setting::set('ventes', 'paiement_sur_bl', 'true');

        $bl      = $this->makeDeliveryNote(500, '2025-01-01');
        $invoice = $this->makeInvoice(500, '2025-02-01');

        $this->bulkPay([
            'amount'  => 500,
            'method'  => 'cash',
            'paid_at' => now()->format('Y-m-d'),
        ])->assertOk();

        // BL is payable now → FIFO picks it first, invoice untouched.
        $this->assertEquals(0,   (float) $bl->fresh()->footer->amount_due);
        $this->assertEquals(500, (float) $invoice->fresh()->footer->amount_due);
        $this->assertSame('paid', $bl->fresh()->status);
    }

    public function test_bulk_payment_never_overpays_a_document(): void
    {
        $invoice = $this->makeInvoice(300, '2025-01-01');

        // Send more than the total due; only 300 should be applied.
        $this->bulkPay([
            'amount'  => 1000,
            'method'  => 'cash',
            'paid_at' => now()->format('Y-m-d'),
        ])->assertOk();

        $invoice->refresh();
        $this->assertEquals(300, (float) $invoice->footer->amount_paid);
        $this->assertEquals(0,   (float) $invoice->footer->amount_due);
        // Only one payment row for 300, not 1000.
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'document_header_id' => $invoice->id,
            'amount'             => 300,
        ]);
    }

    public function test_bulk_payment_returns_422_when_no_unpaid_docs(): void
    {
        // No invoices at all — bulk pay must reject.
        $this->bulkPay([
            'amount'  => 100,
            'method'  => 'cash',
            'paid_at' => now()->format('Y-m-d'),
        ])->assertStatus(422);
    }
}
