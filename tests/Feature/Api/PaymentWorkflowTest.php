<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function createInvoiceWithFooter(float $total = 1000): DocumentHeader
    {
        $doc = DocumentHeader::factory()->invoice()->create([
            'user_id'       => $this->admin->id,
            'document_type' => 'InvoiceSale',
            'status'        => 'confirmed',
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ttc'          => $total,
            'amount_due'         => $total,
            'amount_paid'        => 0,
        ]);

        return $doc;
    }

    // ── Payment creation ──────────────────────────────────────────

    public function test_create_payment_on_invoice(): void
    {
        $doc = $this->createInvoiceWithFooter(1000);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/payments', [
                'document_header_id' => $doc->id,
                'amount'             => 500,
                'method'             => 'cash',
                'paid_at'            => now()->format('Y-m-d'),
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('payments', [
            'document_header_id' => $doc->id,
            'amount'             => 500,
            'method'             => 'cash',
        ]);
    }

    public function test_full_payment_marks_document_as_paid(): void
    {
        $doc = $this->createInvoiceWithFooter(500);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/payments', [
                'document_header_id' => $doc->id,
                'amount'             => 500,
                'method'             => 'bank_transfer',
                'paid_at'            => now()->format('Y-m-d'),
            ])
            ->assertCreated();

        $doc->refresh();
        $footer = $doc->footer;

        $this->assertEquals(0, (float) $footer->amount_due);
        $this->assertEquals('paid', $doc->status);
    }

    public function test_partial_payment_marks_document_as_partial(): void
    {
        $doc = $this->createInvoiceWithFooter(1000);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/payments', [
                'document_header_id' => $doc->id,
                'amount'             => 300,
                'method'             => 'cheque',
                'paid_at'            => now()->format('Y-m-d'),
            ])
            ->assertCreated();

        $doc->refresh();
        $this->assertEquals('partial', $doc->status);
        $this->assertEquals(700, (float) $doc->footer->amount_due);
    }

    public function test_multiple_payments_sum_correctly(): void
    {
        $doc = $this->createInvoiceWithFooter(1000);

        // First payment
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/payments', [
                'document_header_id' => $doc->id,
                'amount'             => 300,
                'method'             => 'cash',
                'paid_at'            => now()->format('Y-m-d'),
            ])
            ->assertCreated();

        // Second payment
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/payments', [
                'document_header_id' => $doc->id,
                'amount'             => 700,
                'method'             => 'bank_transfer',
                'paid_at'            => now()->format('Y-m-d'),
            ])
            ->assertCreated();

        $doc->refresh();
        $this->assertEquals(0, (float) $doc->footer->amount_due);
        $this->assertEquals('paid', $doc->status);
    }

    // ── Payment deletion ──────────────────────────────────────────

    public function test_delete_payment_recalculates_footer(): void
    {
        $doc = $this->createInvoiceWithFooter(1000);

        $paymentResp = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/payments', [
                'document_header_id' => $doc->id,
                'amount'             => 1000,
                'method'             => 'cash',
                'paid_at'            => now()->format('Y-m-d'),
            ]);

        $paymentId = $paymentResp->json('id');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/payments/{$paymentId}")
            ->assertNoContent();

        $doc->refresh();
        $this->assertEquals(1000, (float) $doc->footer->amount_due);
    }

    // ── Validation ────────────────────────────────────────────────

    public function test_payment_requires_valid_fields(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/payments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_header_id', 'amount', 'method']);
    }

    public function test_payment_amount_must_be_positive(): void
    {
        $doc = $this->createInvoiceWithFooter();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/payments', [
                'document_header_id' => $doc->id,
                'amount'             => -50,
                'method'             => 'cash',
            ])
            ->assertUnprocessable();
    }

    // ── Authorization ─────────────────────────────────────────────

    public function test_warehouse_user_cannot_create_payment(): void
    {
        $whUser = User::factory()->warehouse()->create();
        $doc    = $this->createInvoiceWithFooter();

        $this->actingAs($whUser, 'sanctum')
            ->postJson('/api/payments', [
                'document_header_id' => $doc->id,
                'amount'             => 100,
                'method'             => 'cash',
            ])
            ->assertForbidden();
    }

    public function test_cashier_can_create_payment(): void
    {
        $cashier = User::factory()->cashier()->create();
        $doc     = $this->createInvoiceWithFooter();

        $this->actingAs($cashier, 'sanctum')
            ->postJson('/api/payments', [
                'document_header_id' => $doc->id,
                'amount'             => 100,
                'method'             => 'cash',
                'paid_at'            => now()->format('Y-m-d'),
            ])
            ->assertCreated();
    }
}
