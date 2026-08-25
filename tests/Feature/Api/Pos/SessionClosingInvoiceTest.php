<?php

namespace Tests\Feature\Api\Pos;

use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\StockMouvement;
use App\Models\ThirdPartner;
use App\Models\User;
use Tests\Concerns\InteractsWithPos;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Récapitulation des tickets d'une session en factures, à la clôture.
 *
 * Une session mélange couramment des ventes au comptoir et des ventes
 * nominatives : le regroupement se fait donc client par client, jamais en un
 * seul document pour toute la caisse.
 */
class SessionClosingInvoiceTest extends TestCase
{
    use RefreshTenantDatabase;
    use InteractsWithPos;

    private User $cashier;
    private ThirdPartner $comptoir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTenant();
        $this->setUpPosTerminal();
        $this->setUpPosIncrementors();
        $this->comptoir = $this->setUpComptoirCustomer();

        DocumentIncrementor::firstOrCreate(
            ['di_model' => 'InvoiceSale'],
            [
                'di_title'     => 'Facture Vente',
                'di_domain'    => 'sales',
                'template'     => 'FV-{NNNN}',
                'nextTrick'    => 1,
                'status'       => true,
                'operatorSens' => 'out',
            ],
        );

        $this->cashier = User::factory()->cashier()->create();
        $this->grantPermissions($this->cashier, 'pos.access', 'pos.open_session', 'pos.close_session');
    }

    private function enableClosingInvoice(bool $on = true): void
    {
        Setting::updateOrCreate(
            ['st_domain' => 'pos', 'st_key' => 'facture_cloture'],
            ['st_value' => $on ? 'true' : 'false'],
        );
    }

    private function openSession(): int
    {
        return $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', [
                'pos_terminal_id' => $this->terminal->id,
                'opening_cash'    => 0,
            ])->json('id');
    }

    private function sell(float $amount, ?int $customerId = null): void
    {
        $product = $this->stockedProduct(salePrice: $amount, stock: 100);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 1, 'unit_price' => $amount, 'tax_percent' => 0]],
                [['amount' => $amount, 'method' => 'cash']],
                $customerId,
            ))->assertCreated();
    }

    private function closeSession(int $sessionId): void
    {
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/sessions/{$sessionId}/close", ['closing_cash' => 0])
            ->assertOk();
    }

    private function invoices()
    {
        return DocumentHeader::where('document_type', 'InvoiceSale')->get();
    }

    // ── Comportement nominal ──────────────────────────────────────

    public function test_closing_bills_the_session_tickets_as_one_invoice(): void
    {
        $this->enableClosingInvoice();

        $session = $this->openSession();
        $this->sell(300);
        $this->sell(200);
        $this->closeSession($session);

        $invoices = $this->invoices();

        $this->assertCount(1, $invoices);
        $this->assertSame(500.0, (float) $invoices->first()->footer->total_ttc);
        $this->assertStringContainsString('Tickets :', $invoices->first()->notes);
        $this->assertStringContainsString('Session caisse #', $invoices->first()->notes);
    }

    public function test_the_invoice_is_born_paid_with_the_ticket_payments(): void
    {
        $this->enableClosingInvoice();

        $session = $this->openSession();
        $this->sell(300);
        $this->sell(200);
        $this->closeSession($session);

        $invoice = $this->invoices()->first();

        // Un ticket est toujours regle : la facture qui le recapitule l'est aussi.
        $this->assertSame(500.0, (float) $invoice->footer->amount_paid);
        $this->assertSame(0.0, (float) $invoice->footer->amount_due);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(2, Payment::where('document_header_id', $invoice->id)->count());
    }

    /**
     * Le point qui interdit la facture unique par session.
     */
    public function test_tickets_of_two_customers_give_two_invoices(): void
    {
        $this->enableClosingInvoice();

        $named = ThirdPartner::factory()->create(['tp_title' => 'Client nommé', 'tp_Role' => 'customer']);

        $session = $this->openSession();
        $this->sell(300);                      // comptoir
        $this->sell(700, $named->id);          // client nommé
        $this->closeSession($session);

        $invoices = $this->invoices();

        $this->assertCount(2, $invoices);
        $this->assertEqualsCanonicalizing(
            [$this->comptoir->id, $named->id],
            $invoices->pluck('thirdPartner_id')->all(),
        );
        $this->assertSame(700.0, (float) $invoices->firstWhere('thirdPartner_id', $named->id)->footer->total_ttc);
    }

    public function test_the_tickets_survive_and_cannot_be_billed_twice(): void
    {
        $this->enableClosingInvoice();

        $session = $this->openSession();
        $this->sell(300);
        $this->closeSession($session);

        $ticket = DocumentHeader::where('document_type', 'TicketSale')->firstOrFail();

        // Le ticket reste : c'est le justificatif remis au client.
        $this->assertSame('converted', $ticket->status);

        // Refacturer la meme session ne cree rien de plus.
        app(\App\Services\PosService::class)->invoiceSessionTickets(
            \App\Models\PosSession::findOrFail($session)
        );

        $this->assertCount(1, $this->invoices());
    }

    public function test_it_does_not_move_stock_again(): void
    {
        $this->enableClosingInvoice();

        $session = $this->openSession();
        $this->sell(300);

        $before = StockMouvement::count();
        $this->closeSession($session);

        // La marchandise est sortie au ticket, pas a la facturation.
        $this->assertSame($before, StockMouvement::count());
    }

    // ── Réglage ───────────────────────────────────────────────────

    public function test_nothing_happens_when_the_setting_is_off(): void
    {
        $this->enableClosingInvoice(false);

        $session = $this->openSession();
        $this->sell(300);
        $this->closeSession($session);

        $this->assertCount(0, $this->invoices());
        $this->assertSame('paid', DocumentHeader::where('document_type', 'TicketSale')->firstOrFail()->status);
    }

    public function test_the_setting_is_off_by_default(): void
    {
        $session = $this->openSession();
        $this->sell(300);
        $this->closeSession($session);

        $this->assertCount(0, $this->invoices());
    }

    public function test_a_session_without_tickets_closes_quietly(): void
    {
        $this->enableClosingInvoice();

        $session = $this->openSession();
        $this->closeSession($session);

        $this->assertCount(0, $this->invoices());
    }
}
