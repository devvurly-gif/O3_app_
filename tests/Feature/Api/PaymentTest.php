<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function createDocumentWithFooter(array $footerOverrides = []): DocumentHeader
    {
        $doc = DocumentHeader::factory()->invoice()->create(['user_id' => $this->admin->id]);
        DocumentFooter::factory()->create(array_merge([
            'document_header_id' => $doc->id,
            'total_ttc'          => 1000,
            'amount_due'         => 1000,
            'amount_paid'        => 0,
        ], $footerOverrides));

        return $doc;
    }

    private function createPaymentWithDoc(array $paymentOverrides = []): Payment
    {
        $doc = $this->createDocumentWithFooter();

        return Payment::factory()->create(array_merge([
            'document_header_id' => $doc->id,
            'method'             => 'cash',
        ], $paymentOverrides));
    }

    public function test_index_returns_paginated_payments(): void
    {
        $this->createPaymentWithDoc();
        $this->createPaymentWithDoc();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/payments')
             ->assertOk()
             ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_show_returns_single_payment(): void
    {
        $payment = $this->createPaymentWithDoc();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson("/api/payments/{$payment->id}")
             ->assertOk()
             ->assertJsonFragment(['id' => $payment->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/payments', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['document_header_id', 'amount', 'method']);
    }

    public function test_store_validates_minimum_amount(): void
    {
        $doc = $this->createDocumentWithFooter();

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/payments', [
                 'document_header_id' => $doc->id,
                 'amount'             => 0,
                 'method'             => 'cash',
             ])
             ->assertUnprocessable();
    }

    public function test_store_validates_document_exists(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/payments', [
                 'document_header_id' => 99999,
                 'amount'             => 100,
                 'method'             => 'cash',
             ])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['document_header_id']);
    }

    public function test_delete_removes_payment(): void
    {
        $payment = $this->createPaymentWithDoc();

        $this->actingAs($this->admin, 'sanctum')
             ->deleteJson("/api/payments/{$payment->id}")
             ->assertNoContent();
    }

    public function test_warehouse_user_cannot_create_payment(): void
    {
        $whUser = User::factory()->warehouse()->create();
        $doc    = $this->createDocumentWithFooter();

        $this->actingAs($whUser, 'sanctum')
             ->postJson('/api/payments', [
                 'document_header_id' => $doc->id,
                 'amount'             => 100,
                 'method'             => 'cash',
             ])
             ->assertForbidden();
    }

    public function test_cashier_cannot_delete_others_payment(): void
    {
        $cashier = User::factory()->cashier()->create();
        $payment = $this->createPaymentWithDoc();

        $this->actingAs($cashier, 'sanctum')
             ->deleteJson("/api/payments/{$payment->id}")
             ->assertNoContent();
    }

    public function test_index_filters_by_method(): void
    {
        $this->createPaymentWithDoc(['method' => 'cash']);
        $this->createPaymentWithDoc(['method' => 'cheque']);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/payments?method=cash');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
