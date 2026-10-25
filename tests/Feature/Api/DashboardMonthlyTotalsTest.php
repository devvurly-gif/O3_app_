<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\ThirdPartner;
use App\Models\User;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « CA Ventes du mois » et « Achats du mois » : factures + bons confirmés pas encore facturés, avec le détail
 * (meta.invoices / meta.receipts) ; un bon déjà facturé, brouillon ou annulé ne compte pas, ni un document d'un autre mois.
 */
class DashboardMonthlyTotalsTest extends TestCase
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
        $cards = $this->actingAs($this->admin, 'sanctum')->getJson('/api/dashboard')->assertOk()->json('cards');

        return collect($cards)->firstWhere('key', $key) ?? $this->fail("Carte « {$key} » absente.");
    }

    private function doc(string $type, string $status, float $ttc, string $role, ?\DateTimeInterface $at = null, ?int $parent = null): DocumentHeader
    {
        $doc = DocumentHeader::factory()->create([
            'document_type'   => $type,
            'status'          => $status,
            'issued_at'       => $at ?? now(),
            'parent_id'       => $parent,
            'thirdPartner_id' => ThirdPartner::factory()->create(['tp_Role' => $role])->id,
        ]);
        DocumentFooter::factory()->create(['document_header_id' => $doc->id, 'total_ht' => $ttc, 'total_tax' => 0, 'total_ttc' => $ttc, 'amount_paid' => 0, 'amount_due' => $ttc]);

        return $doc;
    }

    public function test_sales_card_adds_confirmed_delivery_notes_not_yet_invoiced(): void
    {
        $this->doc('InvoiceSale', 'confirmed', 1000, 'customer');
        $this->doc('TicketSale', 'paid', 200, 'customer');
        $this->doc('DeliveryNote', 'confirmed', 500, 'customer');
        $billed = $this->doc('DeliveryNote', 'confirmed', 700, 'customer');          // déjà facturé : compté via sa facture
        $this->doc('InvoiceSale', 'confirmed', 0, 'customer', parent: $billed->id);
        $this->doc('DeliveryNote', 'draft', 900, 'customer');
        $this->doc('DeliveryNote', 'cancelled', 900, 'customer');
        $this->doc('DeliveryNote', 'confirmed', 900, 'customer', now()->subMonths(2));

        $card = $this->card('ca_month');

        $this->assertEquals(1700, $card['value']);                                     // 1000 + 200 + 500
        $this->assertEquals(['invoices' => 1200, 'receipts' => 500], array_map('floatval', $card['meta']));
    }

    public function test_purchases_card_shows_invoices_and_confirmed_receipt_notes_apart(): void
    {
        $this->doc('InvoicePurchase', 'confirmed', 300, 'supplier');
        $this->doc('ReceiptNotePurchase', 'confirmed', 840, 'supplier');
        $this->doc('ReceiptNotePurchase', 'confirmed', 840, 'supplier', now()->subMonths(2));   // autre mois
        $this->doc('ReceiptNotePurchase', 'draft', 100, 'supplier');

        $card = $this->card('purchases_month');

        $this->assertEquals(1140, $card['value']);
        $this->assertEquals(['invoices' => 300, 'receipts' => 840], array_map('floatval', $card['meta']));
    }
}
