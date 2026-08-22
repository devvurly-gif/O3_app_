<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\ThirdPartner;
use App\Models\User;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Créances et encaissements du tableau de bord.
 *
 * Deux défauts relevés en réconciliant le tableau de bord d'un tenant avec sa
 * comptabilité manuelle, et corrigés ici :
 *
 *   - la carte « Créances en cours » ne comptait que InvoiceSale et TicketSale.
 *     Or une vente à crédit au POS est enregistrée en DeliveryNote
 *     (PosService::createTicket), donc aucune créance POS n'a jamais pu y
 *     apparaître : le tenant affichait 0 avec 15 600 MAD réellement dus ;
 *
 *   - la carte « Encaissements du mois » sommait tous les modes de paiement,
 *     y compris `credit`. Une vente en compte est écrite comme une ligne de
 *     paiement pour équilibrer le ticket, mais aucun argent n'entre : le même
 *     dirham était compté comme encaissé et comme dû.
 */
class DashboardReceivablesTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<string, mixed> */
    private function card(string $key): array
    {
        $cards = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard')
            ->assertOk()
            ->json('cards');

        foreach ($cards as $card) {
            if (($card['key'] ?? null) === $key) {
                return $card;
            }
        }

        $this->fail("Carte « {$key} » absente du tableau de bord.");
    }

    private function saleWithFooter(string $type, string $status, float $ttc, float $paid): DocumentHeader
    {
        $doc = DocumentHeader::factory()->create([
            'document_type'   => $type,
            'status'          => $status,
            'issued_at'       => now(),
            'thirdPartner_id' => ThirdPartner::factory()->create(['tp_Role' => 'customer'])->id,
        ]);

        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ht'           => $ttc,
            'total_tax'          => 0,
            'total_ttc'          => $ttc,
            'amount_paid'        => $paid,
            'amount_due'         => $ttc - $paid,
        ]);

        return $doc;
    }

    // ── Créances ─────────────────────────────────────────────────

    public function test_receivables_include_pos_credit_sales(): void
    {
        // Vente en compte au POS : un BL confirmé, intégralement dû.
        $this->saleWithFooter('DeliveryNote', 'confirmed', 2500, 0);

        $this->assertEquals(2500, $this->card('outstanding')['value']);
    }

    public function test_receivables_still_include_unpaid_invoices(): void
    {
        $this->saleWithFooter('InvoiceSale', 'pending', 1000, 400);
        $this->saleWithFooter('DeliveryNote', 'confirmed', 2500, 0);

        $this->assertEquals(3100, $this->card('outstanding')['value'], '600 dus + 2 500 en compte');
    }

    public function test_a_delivery_note_already_invoiced_is_not_counted_twice(): void
    {
        $bl = $this->saleWithFooter('DeliveryNote', 'delivered', 2500, 0);

        // La facture fille porte la même créance ; compter les deux la doublerait.
        $invoice = $this->saleWithFooter('InvoiceSale', 'pending', 2500, 0);
        $invoice->update(['parent_id' => $bl->id]);

        $this->assertEquals(2500, $this->card('outstanding')['value']);
    }

    public function test_paid_and_cancelled_sales_are_excluded(): void
    {
        $this->saleWithFooter('TicketSale', 'paid', 800, 800);
        $this->saleWithFooter('DeliveryNote', 'cancelled', 1500, 0);

        $this->assertEquals(0, $this->card('outstanding')['value']);
    }

    // ── Encaissements ────────────────────────────────────────────

    public function test_credit_is_not_counted_as_money_received(): void
    {
        $ticket = $this->saleWithFooter('TicketSale', 'paid', 1000, 1000);
        $bl     = $this->saleWithFooter('DeliveryNote', 'confirmed', 2500, 0);

        Payment::$skipNotification = true;

        try {
            Payment::create([
                'payment_code'       => 'PAY-CASH',
                'document_header_id' => $ticket->id,
                'amount'             => 1000,
                'method'             => 'cash',
                'paid_at'            => now(),
                'user_id'            => $this->admin->id,
            ]);

            // Écrit pour équilibrer le ticket, mais aucun argent n'entre.
            Payment::create([
                'payment_code'       => 'PAY-CREDIT',
                'document_header_id' => $bl->id,
                'amount'             => 2500,
                'method'             => 'credit',
                'paid_at'            => now(),
                'user_id'            => $this->admin->id,
            ]);
        } finally {
            Payment::$skipNotification = false;
        }

        $card = $this->card('payments_month');

        $this->assertEquals(1000, $card['value'], 'seul le règlement en espèces est un encaissement');
        $this->assertEquals(2500, $card['meta']['credit_granted'], 'le crédit reste visible, à part');
    }
}
