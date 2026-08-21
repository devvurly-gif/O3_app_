<?php

namespace Tests\Feature\Api\Pos;

use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\ThirdPartner;
use App\Models\User;
use Tests\Concerns\InteractsWithPos;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Selling at the till: cash tickets, credit ("en compte") sales, voids and
 * returns — including their effect on stock, payments and customer encours.
 *
 * Payment-amount integrity is deliberately not covered here; it lives in
 * PosPaymentIntegrityTest alongside the other known defects.
 */
class PosTicketTest extends TestCase
{
    use RefreshTenantDatabase;
    use InteractsWithPos;

    private User $cashier;
    private PosSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTenant();
        $this->setUpPosTerminal();
        $this->setUpPosIncrementors();
        $this->setUpComptoirCustomer();

        $this->cashier = User::factory()->cashier()->create();
        $this->grantPermissions(
            $this->cashier,
            'pos.access',
            'pos.open_session',
            'pos.close_session',
            'pos.void_ticket',
        );

        $this->session = $this->openSessionFor($this->cashier, 200);
    }

    // ── Cash sale ────────────────────────────────────────────────

    public function test_a_cash_sale_creates_a_ticket_and_deducts_stock(): void
    {
        $product = $this->stockedProduct(salePrice: 100, stock: 50);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 3]],
                [['amount' => 300, 'method' => 'cash']],
            ));

        $response->assertCreated()
            ->assertJsonPath('document_type', 'TicketSale')
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('pos_session_id', $this->session->id);

        $ticket = DocumentHeader::find($response->json('id'));

        $this->assertNotEmpty($ticket->reference);
        $this->assertSame($this->incrementorFor('TicketSale')->id, $ticket->document_incrementor_id);
        $this->assertEquals(300, (float) $ticket->footer->total_ttc);
        $this->assertEquals(300, (float) $ticket->footer->amount_paid);
        $this->assertSame(1, $ticket->lignes()->count());
        $this->assertEquals(47, $this->stockLevel($product), '50 - 3 vendus');
    }

    public function test_line_totals_apply_discount_before_tax(): void
    {
        $product = $this->stockedProduct(salePrice: 100, stock: 20);

        // 2 × 100 = 200, remise 10% → 180 HT, TVA 20% → 36, TTC 216.
        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', [
                'items' => [[
                    'product_id'       => $product->id,
                    'designation'      => $product->p_title,
                    'quantity'         => 2,
                    'unit_price'       => 100,
                    'discount_percent' => 10,
                    'tax_percent'      => 20,
                ]],
                'payments' => [['amount' => 216, 'method' => 'cash']],
            ]);

        $response->assertCreated();

        $footer = DocumentHeader::find($response->json('id'))->footer;

        $this->assertEquals(180, (float) $footer->total_ht);
        $this->assertEquals(36, (float) $footer->total_tax);
        $this->assertEquals(216, (float) $footer->total_ttc);
    }

    public function test_a_sale_without_a_customer_falls_back_to_client_comptoir(): void
    {
        $product  = $this->stockedProduct();
        $comptoir = ThirdPartner::where('tp_code', 'CLIENT-COMPTOIR')->first();

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 100, 'method' => 'cash']],
            ));

        $response->assertCreated()
            ->assertJsonPath('thirdPartner_id', $comptoir->id);
    }

    public function test_a_split_payment_is_recorded_line_by_line(): void
    {
        $product = $this->stockedProduct(salePrice: 100, stock: 10);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 2]],
                [
                    ['amount' => 120, 'method' => 'cash'],
                    ['amount' => 80,  'method' => 'card'],
                ],
            ));

        $response->assertCreated();

        $payments = Payment::where('document_header_id', $response->json('id'))->get();

        $this->assertCount(2, $payments);
        $this->assertEqualsCanonicalizing(['cash', 'card'], $payments->pluck('method')->all());
        $this->assertEquals(200, (float) $payments->sum('amount'));
    }

    // ── Credit sale ──────────────────────────────────────────────

    public function test_a_credit_sale_produces_a_delivery_note_and_raises_encours(): void
    {
        $customer = ThirdPartner::factory()->create([
            'type_compte'  => 'en_compte',
            'seuil_credit' => 10000,
        ]);
        $product = $this->stockedProduct(salePrice: 500, stock: 10);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 2]],
                [['amount' => 1000, 'method' => 'credit']],
                $customer->id,
            ));

        $response->assertCreated()
            ->assertJsonPath('document_type', 'DeliveryNote')
            ->assertJsonPath('status', 'confirmed');

        $this->assertSame(
            $this->incrementorFor('DeliveryNote')->id,
            $response->json('document_incrementor_id'),
            'une vente à crédit est numérotée sur le compteur BL',
        );
        $this->assertEquals(1000, (float) $customer->fresh()->encours_actuel);
        $this->assertEquals(8, $this->stockLevel($product));
    }

    public function test_a_normal_customer_cannot_pay_on_account(): void
    {
        $customer = ThirdPartner::factory()->create(['type_compte' => 'normal']);
        $product  = $this->stockedProduct();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 100, 'method' => 'credit']],
                $customer->id,
            ))
            ->assertStatus(422);
    }

    public function test_a_credit_sale_beyond_the_ceiling_is_refused(): void
    {
        $customer = ThirdPartner::factory()->create([
            'type_compte'  => 'en_compte',
            'seuil_credit' => 500,
        ]);
        $product = $this->stockedProduct(salePrice: 1000, stock: 10);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 1000, 'method' => 'credit']],
                $customer->id,
            ))
            ->assertStatus(422);

        // The whole ticket is rolled back: no document, no stock movement.
        $this->assertSame(0, DocumentHeader::count());
        $this->assertEquals(10, $this->stockLevel($product));
    }

    // ── Guard rails ──────────────────────────────────────────────

    public function test_selling_without_an_open_session_is_refused(): void
    {
        $this->session->update(['closed_at' => now()]);
        $product = $this->stockedProduct();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 100, 'method' => 'cash']],
            ))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Votre session de caisse est fermée. Veuillez ouvrir une nouvelle session pour encaisser.');
    }

    public function test_a_ticket_needs_at_least_one_item_and_one_payment(): void
    {
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', ['items' => [], 'payments' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items', 'payments']);
    }

    public function test_quantities_and_payment_methods_are_validated(): void
    {
        $product = $this->stockedProduct();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', [
                'items' => [[
                    'product_id'  => $product->id,
                    'designation' => $product->p_title,
                    'quantity'    => 0,
                    'unit_price'  => 100,
                ]],
                'payments' => [['amount' => 100, 'method' => 'bitcoin']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity', 'payments.0.method']);
    }

    public function test_a_missing_numbering_row_fails_cleanly(): void
    {
        DocumentIncrementor::where('di_model', 'TicketSale')->delete();
        $product = $this->stockedProduct();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 100, 'method' => 'cash']],
            ))
            ->assertStatus(422);
    }

    // ── History ──────────────────────────────────────────────────

    public function test_ticket_history_is_scoped_to_the_current_session(): void
    {
        $product = $this->stockedProduct(salePrice: 100, stock: 20);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 100, 'method' => 'cash']],
            ))->assertCreated();

        // A ticket from someone else's session must not leak into this list.
        $otherSession = PosSession::factory()->create([
            'pos_terminal_id' => $this->terminal->id,
            'user_id'         => User::factory()->cashier()->create()->id,
        ]);
        DocumentHeader::factory()->create([
            'document_type'  => 'TicketSale',
            'pos_session_id' => $otherSession->id,
        ]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/pos/tickets');

        $response->assertOk()->assertJsonCount(1);
        $this->assertSame($this->session->id, $response->json('0.pos_session_id'));
    }

    // ── Void ─────────────────────────────────────────────────────

    public function test_voiding_a_ticket_restores_stock_and_drops_payments(): void
    {
        $product = $this->stockedProduct(salePrice: 100, stock: 50);

        $ticketId = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 4]],
                [['amount' => 400, 'method' => 'cash']],
            ))->json('id');

        $this->assertEquals(46, $this->stockLevel($product));

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/tickets/{$ticketId}/void")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertEquals(50, $this->stockLevel($product), 'stock rendu');
        $this->assertSame(0, Payment::where('document_header_id', $ticketId)->count());

        $footer = DocumentHeader::find($ticketId)->footer;
        $this->assertEquals(0, (float) $footer->amount_paid);
        $this->assertEquals(0, (float) $footer->amount_due);
    }

    public function test_a_ticket_cannot_be_voided_twice(): void
    {
        $product = $this->stockedProduct(salePrice: 100, stock: 10);

        $ticketId = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 100, 'method' => 'cash']],
            ))->json('id');

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/tickets/{$ticketId}/void")->assertOk();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/tickets/{$ticketId}/void")
            ->assertStatus(422);

        $this->assertEquals(10, $this->stockLevel($product), 'pas de double rendu');
    }

    public function test_void_rejects_documents_that_are_not_pos_tickets(): void
    {
        $invoice = DocumentHeader::factory()->create([
            'document_type'  => 'InvoiceSale',
            'pos_session_id' => $this->session->id,
        ]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/tickets/{$invoice->id}/void")
            ->assertStatus(422);
    }

    public function test_a_cashier_cannot_void_another_sessions_ticket(): void
    {
        $otherSession = PosSession::factory()->create([
            'pos_terminal_id' => $this->terminal->id,
            'user_id'         => User::factory()->cashier()->create()->id,
        ]);
        $foreign = DocumentHeader::factory()->create([
            'document_type'  => 'TicketSale',
            'pos_session_id' => $otherSession->id,
        ]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/tickets/{$foreign->id}/void")
            ->assertForbidden();

        $this->assertSame('confirmed', $foreign->fresh()->status);
    }

    public function test_a_manager_can_void_any_ticket(): void
    {
        $product = $this->stockedProduct(salePrice: 100, stock: 10);

        $ticketId = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 100, 'method' => 'cash']],
            ))->json('id');

        $manager = User::factory()->manager()->create();
        $this->grantPermissions($manager, 'pos.access', 'pos.void_ticket');

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/pos/tickets/{$ticketId}/void")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }

    // ── Return ───────────────────────────────────────────────────

    public function test_returning_a_credit_sale_creates_a_return_note_and_frees_credit(): void
    {
        $customer = ThirdPartner::factory()->create([
            'type_compte'  => 'en_compte',
            'seuil_credit' => 10000,
        ]);
        $product = $this->stockedProduct(salePrice: 500, stock: 10);

        $blId = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 2]],
                [['amount' => 1000, 'method' => 'credit']],
                $customer->id,
            ))->json('id');

        $this->assertEquals(1000, (float) $customer->fresh()->encours_actuel);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/tickets/{$blId}/retour");

        $response->assertOk()->assertJsonPath('document_type', 'ReturnSale');

        $this->assertSame(
            $this->incrementorFor('ReturnSale')->id,
            $response->json('document_incrementor_id'),
        );
        $this->assertSame($blId, $response->json('parent_id'));
        $this->assertEquals(10, $this->stockLevel($product), 'marchandise rendue');
        $this->assertEquals(0, (float) $customer->fresh()->encours_actuel, 'crédit libéré');
    }

    public function test_returning_a_cash_ticket_voids_it(): void
    {
        $product = $this->stockedProduct(salePrice: 100, stock: 10);

        $ticketId = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 2]],
                [['amount' => 200, 'method' => 'cash']],
            ))->json('id');

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/tickets/{$ticketId}/retour")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertEquals(10, $this->stockLevel($product));
    }

    public function test_a_return_is_refused_for_unsupported_document_types(): void
    {
        $quote = DocumentHeader::factory()->quote()->create([
            'pos_session_id' => $this->session->id,
        ]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/tickets/{$quote->id}/retour")
            ->assertStatus(422);
    }
}
