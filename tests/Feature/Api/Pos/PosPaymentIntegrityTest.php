<?php

namespace Tests\Feature\Api\Pos;

use App\Models\DocumentHeader;
use App\Models\ThirdPartner;
use App\Models\User;
use Tests\Concerns\InteractsWithPos;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Money integrity at the till.
 *
 * Three tests in this class FAIL against the current code on purpose: they
 * assert the behaviour the till should have, and they turn green when the
 * defects are fixed. They are isolated here so the rest of the POS suite
 * stays a clean regression net.
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
 * To keep CI green before the fixes land, mark the two failing tests with
 * $this->markTestSkipped('C-02') rather than deleting them.
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
        $this->grantPermissions($this->cashier, 'pos.access', 'pos.open_session');
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
     * The counterpart that already passes: the footer arithmetic itself is
     * sound. Worth pinning down, because the natural fix for C-02 touches
     * exactly these fields.
     */
    public function test_the_footer_always_reconciles_total_paid_and_due(): void
    {
        $product = $this->stockedProduct(salePrice: 1000, stock: 50);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 5]],
                [['amount' => 1, 'method' => 'cash']],
            ));

        if ($response->status() === 422) {
            $this->markTestSkipped('Le sous-paiement est désormais refusé : plus rien à réconcilier.');
        }

        $footer = DocumentHeader::find($response->json('id'))->footer;

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

    public function test_over_payment_behaviour_is_undecided(): void
    {
        $this->markTestSkipped(
            'En attente d\'arbitrage produit : un paiement supérieur au total doit-il '
            . 'être refusé, ou enregistré avec un rendu de monnaie ? Aujourd\'hui le '
            . 'surplus gonfle amount_paid et fausse le rapprochement de caisse à la clôture.',
        );
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
