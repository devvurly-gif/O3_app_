<?php

namespace Tests\Feature\Api;

use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the GET /api/products/{id}/statistics aggregates.
 *
 * Real-world rule the aggregates must follow:
 *   Sales
 *     - count lines of InvoiceSale + TicketSale (always)
 *     - count lines of DeliveryNote ONLY when not yet converted to InvoiceSale
 *       (BL with an InvoiceSale child is already counted via its child)
 *     - exclude draft / cancelled
 *   Purchases (mirror)
 *     - count lines of InvoicePurchase + StockEntry
 *     - count ReceiptNotePurchase lines ONLY when not yet billed
 *     - exclude draft / cancelled
 *
 * Bug regression: a product with BL-only movement showed "0 sold" on the
 * statistics tab because BL lines were not considered part of the sales total.
 */
class ProductStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin   = User::factory()->admin()->create();
        $this->product = Product::factory()->create();
    }

    private function makeDoc(string $type, string $status = 'confirmed', ?DocumentHeader $parent = null): DocumentHeader
    {
        return DocumentHeader::factory()->create([
            'user_id'       => $this->admin->id,
            'document_type' => $type,
            'status'        => $status,
            'parent_id'     => $parent?->id,
        ]);
    }

    private function makeLine(DocumentHeader $doc, float $qty, float $unitPrice): DocumentLigne
    {
        $ht    = $qty * $unitPrice;
        $tax   = $ht * 0.20;
        return DocumentLigne::factory()->create([
            'document_header_id' => $doc->id,
            'product_id'         => $this->product->id,
            'quantity'           => $qty,
            'unit_price'         => $unitPrice,
            'tax_percent'        => 20,
            'total_ligne_ht'     => $ht,
            'total_tax'          => $tax,
            'total_ttc'          => $ht + $tax,
        ]);
    }

    private function statistics(): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/products/{$this->product->id}/statistics")
            ->assertOk()
            ->json();
    }

    public function test_uninvoiced_bl_is_counted_as_sale(): void
    {
        $bl = $this->makeDoc('DeliveryNote');
        $this->makeLine($bl, 3, 100); // total_ttc = 360

        $stats = $this->statistics();

        $this->assertEquals(3,   $stats['sales']['total_units']);
        $this->assertEquals(360, $stats['sales']['total_revenue']);
        $this->assertEquals(1,   $stats['sales']['count']);
    }

    public function test_bl_converted_to_invoice_is_counted_only_once(): void
    {
        // BL with children=InvoiceSale must be excluded (the invoice line
        // already captures the revenue).
        $bl      = $this->makeDoc('DeliveryNote');
        $invoice = $this->makeDoc('InvoiceSale', 'confirmed', $bl);
        $this->makeLine($bl, 2, 100);      // BL line
        $this->makeLine($invoice, 2, 100); // Invoice line

        $stats = $this->statistics();

        // Only the invoice (2 units @100 → 240 TTC) must count.
        $this->assertEquals(2,   $stats['sales']['total_units']);
        $this->assertEquals(240, $stats['sales']['total_revenue']);
        $this->assertEquals(1,   $stats['sales']['count']);
    }

    public function test_draft_and_cancelled_docs_are_excluded(): void
    {
        $ok        = $this->makeDoc('InvoiceSale', 'confirmed');
        $draft     = $this->makeDoc('InvoiceSale', 'draft');
        $cancelled = $this->makeDoc('InvoiceSale', 'cancelled');
        $this->makeLine($ok, 1, 100);
        $this->makeLine($draft, 10, 100);
        $this->makeLine($cancelled, 10, 100);

        $stats = $this->statistics();

        $this->assertEquals(1, $stats['sales']['total_units'], 'draft + cancelled must not inflate total_units');
    }

    public function test_ticket_pos_sales_are_counted(): void
    {
        $ticket = $this->makeDoc('TicketSale');
        $this->makeLine($ticket, 5, 50); // total_ttc = 300

        $stats = $this->statistics();

        $this->assertEquals(5,   $stats['sales']['total_units']);
        $this->assertEquals(300, $stats['sales']['total_revenue']);
    }

    public function test_stock_entry_is_counted_as_purchase(): void
    {
        // Direct stock-in (initial stock, manual replenishment) must be
        // included in the purchase totals — it's a real cost event.
        $stockEntry = $this->makeDoc('StockEntry');
        $this->makeLine($stockEntry, 10, 20); // ht = 200

        $stats = $this->statistics();

        $this->assertEquals(10,  $stats['purchases']['total_units']);
        $this->assertEquals(200, $stats['purchases']['total_cost']);
    }

    public function test_receipt_note_already_billed_is_not_double_counted(): void
    {
        // Mirror of the BL/InvoiceSale rule on the purchase side.
        $bon     = $this->makeDoc('ReceiptNotePurchase');
        $invoice = $this->makeDoc('InvoicePurchase', 'confirmed', $bon);
        $this->makeLine($bon, 4, 25);
        $this->makeLine($invoice, 4, 25);

        $stats = $this->statistics();

        // Only 4 units from the InvoicePurchase — the ReceiptNote is already
        // billed (has an InvoicePurchase child).
        $this->assertEquals(4,   $stats['purchases']['total_units']);
        $this->assertEquals(100, $stats['purchases']['total_cost']);
    }
}
