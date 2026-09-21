<?php

namespace Tests\Unit\Services;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Services\ReportService;
use Carbon\Carbon;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private ReportService $service;
    private User $user;
    private ThirdPartner $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service  = app(ReportService::class);
        $this->user     = User::factory()->create();
        $this->supplier = ThirdPartner::factory()->create();
    }

    /**
     * Le regroupement de factures d'achat soft-delete ses sources et crée une
     * facture qui les récapitule. Les compter toutes doublait la dépense —
     * c'est ce qui affichait 206 910 DH au lieu de 103 455 chez Jadema.
     */
    public function test_purchases_ignore_source_invoices_replaced_by_a_grouped_one(): void
    {
        foreach ([50000.00, 53455.00] as $ttc) {
            $source = $this->purchaseInvoice($ttc, '2026-08-25');
            $source->delete(); // ce que fait DocumentGroupingService
        }

        $this->purchaseInvoice(103455.00, '2026-08-25');

        $totals = $this->purchasesTotals();

        $this->assertEquals(103455.00, $totals['spending_ttc']);
        $this->assertSame(1, $totals['invoice_count']);
    }

    public function test_purchases_exclude_converted_and_cancelled_invoices(): void
    {
        $this->purchaseInvoice(1000.00, '2026-08-01');
        $this->purchaseInvoice(9999.00, '2026-08-01', ['status' => 'converted']);
        $this->purchaseInvoice(8888.00, '2026-08-01', ['status' => 'cancelled']);
        $this->purchaseInvoice(7777.00, '2026-08-01', ['status' => 'draft']);

        $totals = $this->purchasesTotals();

        $this->assertEquals(1000.00, $totals['spending_ttc']);
        $this->assertSame(1, $totals['invoice_count']);
    }

    /**
     * La période porte sur la date du document, pas sur la date de saisie :
     * une facture d'août ressaisie en septembre reste une dépense d'août.
     */
    public function test_period_filters_on_the_document_date_not_the_entry_date(): void
    {
        $invoice = $this->purchaseInvoice(4200.00, '2026-08-10');
        $invoice->forceFill(['created_at' => '2026-09-20 11:00:00'])->saveQuietly();

        $august = $this->purchasesTotals('2026-08-01', '2026-08-31');

        $this->assertEquals(4200.00, $august['spending_ttc']);
        $this->assertEquals(4200.00, $this->service->purchasesReport(
            Carbon::parse('2026-08-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay(),
        )['daily_spending'][0]['total']);
    }

    /**
     * Un BR facturé n'est plus un document indépendant : sa marchandise est
     * déjà portée par la facture. Le statut ne suffit pas à le voir, le flux
     * Achats laisse le BR en 'received' — c'est la filiation qui tranche.
     */
    public function test_top_products_count_a_receipt_note_only_once_after_invoicing(): void
    {
        $product = Product::factory()->create(['p_title' => 'SCIE A SOL']);

        $receipt = $this->purchaseDocument('ReceiptNotePurchase', 7500.00, '2026-08-05', [
            'status' => 'received',
        ]);
        $this->line($receipt, $product, 3, 2500.00);

        $invoice = $this->purchaseDocument('InvoicePurchase', 7500.00, '2026-08-05', [
            'status'    => 'pending',
            'parent_id' => $receipt->id,
        ]);
        $this->line($invoice, $product, 3, 2500.00);

        $report = $this->service->purchasesReport(
            Carbon::parse('2026-08-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay(),
        );

        $this->assertCount(1, $report['top_products']);
        $this->assertEquals(3, $report['top_products'][0]['total_qty']);
        $this->assertEquals(7500.00, $report['top_products'][0]['total_cost']);
    }

    public function test_top_products_still_count_a_receipt_note_not_yet_invoiced(): void
    {
        $product = Product::factory()->create();

        $receipt = $this->purchaseDocument('ReceiptNotePurchase', 5000.00, '2026-08-05', [
            'status' => 'received',
        ]);
        $this->line($receipt, $product, 2, 2500.00);

        $report = $this->service->purchasesReport(
            Carbon::parse('2026-08-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay(),
        );

        $this->assertCount(1, $report['top_products']);
        $this->assertEquals(2, $report['top_products'][0]['total_qty']);
    }

    public function test_document_counts_match_the_invoice_card(): void
    {
        $this->purchaseInvoice(1000.00, '2026-08-01');
        $this->purchaseInvoice(2000.00, '2026-08-01', ['status' => 'converted']);
        $this->purchaseInvoice(3000.00, '2026-08-01')->delete();

        $report = $this->service->purchasesReport(
            Carbon::parse('2026-08-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay(),
        );

        $byType = collect($report['by_type'])->firstWhere('type', 'InvoicePurchase');

        $this->assertSame($report['totals']['invoice_count'], $byType['count']);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function purchasesTotals(string $from = '2026-07-01', string $to = '2026-09-21'): array
    {
        return $this->service->purchasesReport(
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
        )['totals'];
    }

    private function purchaseInvoice(float $ttc, string $issuedAt, array $attributes = []): DocumentHeader
    {
        return $this->purchaseDocument('InvoicePurchase', $ttc, $issuedAt, $attributes);
    }

    private function purchaseDocument(
        string $type,
        float $ttc,
        string $issuedAt,
        array $attributes = [],
    ): DocumentHeader {
        $header = DocumentHeader::factory()->create(array_merge([
            'document_type'   => $type,
            'document_title'  => $type,
            'company_role'    => 'supplier',
            'thirdPartner_id' => $this->supplier->id,
            'user_id'         => $this->user->id,
            'status'          => 'pending',
            'issued_at'       => $issuedAt,
        ], $attributes));

        DocumentFooter::factory()->create([
            'document_header_id' => $header->id,
            'total_ht'           => $ttc,
            'total_tax'          => 0,
            'total_ttc'          => $ttc,
            'amount_due'         => $ttc,
        ]);

        return $header;
    }

    private function line(DocumentHeader $header, Product $product, float $qty, float $unitPrice): void
    {
        DocumentLigne::create([
            'document_header_id' => $header->id,
            'product_id'         => $product->id,
            'sort_order'         => 1,
            'designation'        => $product->p_title,
            'quantity'           => $qty,
            'unit_price'         => $unitPrice,
            'discount_percent'   => 0,
            'tax_percent'        => 0,
        ]);
    }
}
