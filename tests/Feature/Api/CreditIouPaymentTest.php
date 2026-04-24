<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\ThirdPartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the POS IOU (method="credit") accounting rule:
 *   - credit payments are promises to pay later, NOT actual cash received
 *   - they must NOT inflate amount_paid
 *   - they must NOT reduce amount_due
 *   - they must NOT flip the document status to 'paid' or 'partial'
 *   - the partner's encours (debt) must keep counting the IOU amount
 *
 * Real payments (cash / bank_transfer / cheque / effet) follow the normal
 * path and DO flip BLs and invoices to 'paid' when fully settled.
 *
 * Previously broken: recalculateAmountDue summed every payment row, so a
 * POS BL with a credit IOU was silently being marked "paid" while the
 * partner still owed the money.
 */
class CreditIouPaymentTest extends TestCase
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

    private function makeDeliveryNote(float $total = 1000): DocumentHeader
    {
        $doc = DocumentHeader::factory()->deliveryNote()->create([
            'user_id'         => $this->admin->id,
            'status'          => 'confirmed',
            'thirdPartner_id' => $this->customer->id,
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ttc'          => $total,
            'amount_due'         => $total,
            'amount_paid'        => 0,
        ]);
        return $doc;
    }

    private function makeInvoice(float $total = 1000): DocumentHeader
    {
        $doc = DocumentHeader::factory()->invoice()->create([
            'user_id'         => $this->admin->id,
            'status'          => 'confirmed',
            'thirdPartner_id' => $this->customer->id,
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ttc'          => $total,
            'amount_due'         => $total,
            'amount_paid'        => 0,
        ]);
        return $doc;
    }

    public function test_credit_iou_on_bl_does_not_flip_status_to_paid(): void
    {
        $bl = $this->makeDeliveryNote(1200);

        Payment::create([
            'document_header_id' => $bl->id,
            'amount'             => 1200,
            'method'             => 'credit',
            'paid_at'            => now(),
            'user_id'            => $this->admin->id,
        ]);

        $bl->refresh();
        $this->assertSame('confirmed', $bl->status, 'BL with IOU credit must stay confirmed (Livré), not paid.');
        $this->assertEquals(0, (float) $bl->footer->amount_paid, 'Credit IOU must not count as amount_paid.');
        $this->assertEquals(1200, (float) $bl->footer->amount_due, 'amount_due must stay = total_ttc.');
    }

    public function test_real_cash_payment_on_bl_flips_status_to_paid(): void
    {
        $bl = $this->makeDeliveryNote(800);

        Payment::create([
            'document_header_id' => $bl->id,
            'amount'             => 800,
            'method'             => 'cash',
            'paid_at'            => now(),
            'user_id'            => $this->admin->id,
        ]);

        $bl->refresh();
        $this->assertSame('paid', $bl->status, 'BL fully paid in cash must flip to paid.');
        $this->assertEquals(800, (float) $bl->footer->amount_paid);
        $this->assertEquals(0,   (float) $bl->footer->amount_due);
    }

    public function test_mixed_credit_plus_cash_only_cash_counts_as_paid(): void
    {
        $bl = $this->makeDeliveryNote(1000);

        // IOU: customer signs a promise for 600
        Payment::create([
            'document_header_id' => $bl->id,
            'amount'             => 600,
            'method'             => 'credit',
            'paid_at'            => now(),
            'user_id'            => $this->admin->id,
        ]);

        // Real cash: 400
        Payment::create([
            'document_header_id' => $bl->id,
            'amount'             => 400,
            'method'             => 'cash',
            'paid_at'            => now(),
            'user_id'            => $this->admin->id,
        ]);

        $bl->refresh();
        // Only the 400 cash counts: 1000 - 400 = 600 still due → partial
        $this->assertSame('partial', $bl->status);
        $this->assertEquals(400, (float) $bl->footer->amount_paid);
        $this->assertEquals(600, (float) $bl->footer->amount_due);
    }

    public function test_credit_iou_keeps_partner_encours_at_full_amount(): void
    {
        $bl = $this->makeDeliveryNote(1500);

        Payment::create([
            'document_header_id' => $bl->id,
            'amount'             => 1500,
            'method'             => 'credit',
            'paid_at'            => now(),
            'user_id'            => $this->admin->id,
        ]);

        $this->customer->recalculateEncours();
        // The whole 1500 is owed; credit IOU must not subtract from encours.
        $this->assertEquals(1500, (float) $this->customer->fresh()->encours_actuel);
    }

    public function test_real_cash_reduces_partner_encours(): void
    {
        $invoice = $this->makeInvoice(2000);

        Payment::create([
            'document_header_id' => $invoice->id,
            'amount'             => 500,
            'method'             => 'cash',
            'paid_at'            => now(),
            'user_id'            => $this->admin->id,
        ]);

        $this->customer->recalculateEncours();
        $this->assertEquals(1500, (float) $this->customer->fresh()->encours_actuel);
    }

    public function test_deleting_a_cash_payment_reverts_bl_status(): void
    {
        $bl = $this->makeDeliveryNote(500);

        $payment = Payment::create([
            'document_header_id' => $bl->id,
            'amount'             => 500,
            'method'             => 'cash',
            'paid_at'            => now(),
            'user_id'            => $this->admin->id,
        ]);

        $bl->refresh();
        $this->assertSame('paid', $bl->status);

        $payment->delete();

        $bl->refresh();
        $this->assertEquals(500, (float) $bl->footer->amount_due);
        $this->assertEquals(0,   (float) $bl->footer->amount_paid);
        // Status should go back to a non-paid state
        $this->assertNotSame('paid', $bl->status);
    }
}
