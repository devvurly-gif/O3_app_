<?php

namespace Tests\Feature\Api\Pos;

use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\ThirdPartner;
use App\Models\User;
use Tests\Concerns\InteractsWithPos;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Money integrity at the till.
 *
 * Ces tests ont d'abord été écrits rouges, contre le code défaillant : ils
 * énonçaient le comportement attendu de la caisse plutôt que le comportement
 * observé. Ils sont tous verts depuis que les défauts sont corrigés.
 *
 *   C-02  PosService::createTicket() never compares the payments to the
 *         ticket total, and derives `status` from the credit amount alone.
 *         A 5 000 MAD ticket settled with 1 MAD is stored as "paid".
 *
 *   C-03  PaymentObserver dereferences $payment->document->thirdPartner
 *         without a null check. `thirdPartner_id` is nullable by design, so
 *         any tenant missing the CLIENT-COMPTOIR row 500s on every anonymous
 *         cash sale.
 *
 *   POS-1 Le sur-paiement gonflait amount_paid : un billet de 200 sur un
 *         ticket de 170 enregistrait 200, alors que le tiroir n'en garde que
 *         170. La clôture de caisse attendait donc 30 MAD de trop.
 */
class PosPaymentIntegrityTest extends TestCase
{
    use RefreshTenantDatabase;
    use InteractsWithPos;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTenant();
        $this->setUpPosTerminal();
        $this->setUpPosIncrementors();
        $this->setUpComptoirCustomer();

        $this->cashier = User::factory()->cashier()->create();
        $this->grantPermissions($this->cashier, 'pos.access', 'pos.open_session', 'pos.close_session');
        $this->openSessionFor($this->cashier);
    }

    // ── C-02 · under-payment ─────────────────────────────────────

    /**
     * The invariant holds under either fix: rejecting the ticket outright
     * (422) or recording it as partially settled. Only the current
     * behaviour — accepting it and calling it "paid" — violates it.
     */
    public function test_a_ticket_cannot_be_marked_paid_when_the_payments_do_not_cover_it(): void
    {
        $product = $this->stockedProduct(salePrice: 1000, stock: 50);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 5]],   // 5 000 MAD
                [['amount' => 1, 'method' => 'cash']],        // réglé 1 MAD
            ));

        if ($response->status() === 422) {
            // Refused at the door — nothing to reconcile later.
            $this->assertSame(0, DocumentHeader::count());
            $this->assertEquals(50, $this->stockLevel($product));

            return;
        }

        $response->assertCreated();

        $this->assertNotSame(
            'paid',
            $response->json('status'),
            'Un ticket de 5 000 MAD réglé 1 MAD est enregistré comme soldé : '
            . 'les écrans filtrant sur status = "paid" le comptent comme encaissé '
            . 'alors que amount_due porte encore la créance.',
        );
    }

    /**
     * L'invariant du pied de document, vérifié là où il a encore un sens : une
     * vente réglée en partie au comptant et en partie en compte. Le
     * sous-paiement étant désormais refusé, c'est le seul cas où amount_due
     * peut légitimement être non nul.
     */
    public function test_the_footer_reconciles_total_paid_and_due(): void
    {
        $customer = ThirdPartner::factory()->create([
            'type_compte'  => 'en_compte',
            'seuil_credit' => 10000,
        ]);
        $product = $this->stockedProduct(salePrice: 1000, stock: 10);

        $id = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [
                    ['amount' => 400, 'method' => 'cash'],
                    ['amount' => 600, 'method' => 'credit'],
                ],
                $customer->id,
            ))
            ->assertCreated()
            ->json('id');

        $footer = DocumentHeader::find($id)->footer;

        $this->assertEquals(1000, (float) $footer->total_ttc);
        $this->assertEquals(400, (float) $footer->amount_paid);
        $this->assertEquals(600, (float) $footer->amount_due);
        $this->assertEquals(
            (float) $footer->total_ttc - (float) $footer->amount_paid,
            (float) $footer->amount_due,
            'amount_due doit toujours valoir total_ttc - amount_paid',
        );
    }

    public function test_a_ticket_settled_in_full_is_marked_paid(): void
    {
        $product = $this->stockedProduct(salePrice: 250, stock: 10);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 2]],
                [['amount' => 500, 'method' => 'cash']],
            ))
            ->assertCreated()
            ->assertJsonPath('status', 'paid');
    }

    // ── Rendu de monnaie ─────────────────────────────────────────

    public function test_change_is_recorded_and_only_the_net_stays_in_the_till(): void
    {
        $product = $this->stockedProduct(salePrice: 170, stock: 10);

        // Un billet de 200 sur un ticket de 170.
        $id = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 200, 'method' => 'cash']],
            ))
            ->assertCreated()
            ->json('id');

        $footer = DocumentHeader::find($id)->footer;

        $this->assertEquals(170, (float) $footer->total_ttc);
        $this->assertEquals(170, (float) $footer->amount_paid, 'le tiroir garde le net');
        $this->assertEquals(30, (float) $footer->change_given);
        $this->assertEquals(0, (float) $footer->amount_due);

        // Ce que la clôture de caisse sommera.
        $this->assertEquals(
            170,
            (float) Payment::where('document_header_id', $id)->sum('amount'),
            'les paiements enregistrés valent le total du ticket, pas la somme tendue',
        );
    }

    public function test_the_session_expects_the_net_not_the_note_handed_over(): void
    {
        $product = $this->stockedProduct(salePrice: 170, stock: 10);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 200, 'method' => 'cash']],
            ))->assertCreated();

        $session = PosSession::where('user_id', $this->cashier->id)->firstOrFail();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/close", ['closing_cash' => 170])
            ->assertOk();

        $session->refresh();

        $this->assertEquals(170, (float) $session->expected_cash);
        $this->assertEquals(0, (float) $session->cash_difference, 'aucun écart de caisse');
    }

    public function test_change_comes_out_of_the_cash_line_only(): void
    {
        $product = $this->stockedProduct(salePrice: 170, stock: 10);

        // 100 en carte + 100 en espèces sur un ticket de 170 : les 30 de rendu
        // sortent du tiroir, la carte reste à son montant exact.
        $id = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [
                    ['amount' => 100, 'method' => 'card'],
                    ['amount' => 100, 'method' => 'cash'],
                ],
            ))
            ->assertCreated()
            ->json('id');

        $payments = Payment::where('document_header_id', $id)->pluck('amount', 'method');

        $this->assertEquals(100, (float) $payments['card']);
        $this->assertEquals(70, (float) $payments['cash']);
        $this->assertEquals(30, (float) DocumentHeader::find($id)->footer->change_given);
    }

    public function test_no_change_is_given_on_a_card_only_over_payment(): void
    {
        $product = $this->stockedProduct(salePrice: 170, stock: 10);

        // On ne rend pas de monnaie sur une carte : le règlement doit être exact.
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 200, 'method' => 'card']],
            ))
            ->assertStatus(422);

        $this->assertSame(0, DocumentHeader::count());
        $this->assertEquals(10, $this->stockLevel($product), 'ticket rejeté, stock intact');
    }

    public function test_an_exact_payment_records_no_change(): void
    {
        $product = $this->stockedProduct(salePrice: 170, stock: 10);

        $id = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 170, 'method' => 'cash']],
            ))
            ->assertCreated()
            ->json('id');

        $this->assertNull(DocumentHeader::find($id)->footer->change_given);
    }

    // ── C-03 · payment on a document with no third party ─────────

    public function test_a_cash_sale_survives_a_missing_walk_in_customer(): void
    {
        ThirdPartner::where('tp_code', 'CLIENT-COMPTOIR')->forceDelete();

        $product = $this->stockedProduct(salePrice: 100, stock: 10);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 100, 'method' => 'cash']],
            ));

        $this->assertNotSame(
            500,
            $response->status(),
            'Sans le tiers CLIENT-COMPTOIR, PaymentObserver déréférence un null et '
            . 'toute vente comptant anonyme échoue. Attendu : la vente aboutit sans '
            . 'tiers, ou l\'ouverture de session refuse de démarrer avec un message clair.',
        );
    }

    /**
     * Same defect reached from the sales module rather than the till: a
     * document with no third party is accepted by StoreDocumentHeaderRequest,
     * and paying it goes through the same observer.
     */
    public function test_paying_an_invoice_with_no_third_party_does_not_crash(): void
    {
        $admin = User::factory()->admin()->create();

        $document = DocumentHeader::factory()->create([
            'document_type'   => 'InvoiceSale',
            'thirdPartner_id' => null,
            'status'          => 'confirmed',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/payments', [
                'document_header_id' => $document->id,
                'amount'             => 100,
                'method'             => 'cash',
                'paid_at'            => now()->format('Y-m-d'),
            ]);

        $this->assertNotSame(
            500,
            $response->status(),
            'thirdPartner_id est nullable par migration et accepté par la validation : '
            . 'PaymentObserver doit tolérer un document sans tiers.',
        );
    }
}
