<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    // ═══════════════════════════════════════════════════════════════
    //  SALES REPORT
    // ═══════════════════════════════════════════════════════════════

    public function test_sales_report_returns_correct_structure(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reports/sales?from=2024-01-01&to=2024-12-31');

        $response->assertOk()
            ->assertJsonStructure([
                'period' => ['from', 'to'],
                'totals' => ['revenue_ttc', 'revenue_ht', 'total_tax', 'total_discount', 'invoice_count'],
                'by_type',
                'by_status',
                'top_products',
                'top_clients',
                'payments_by_method',
                'daily_revenue',
            ]);
    }

    public function test_sales_report_calculates_totals(): void
    {
        $doc = DocumentHeader::factory()->create([
            'user_id'       => $this->admin->id,
            'document_type' => 'InvoiceSale',
            'status'        => 'confirmed',
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ht'           => 1000,
            'total_tax'          => 200,
            'total_ttc'          => 1200,
            'total_discount'     => 0,
        ]);

        $from = now()->subYear()->format('Y-m-d');
        $to   = now()->addDay()->format('Y-m-d');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/reports/sales?from={$from}&to={$to}");

        $response->assertOk();
        $totals = $response->json('totals');
        $this->assertEquals(1200, $totals['revenue_ttc']);
        $this->assertEquals(1000, $totals['revenue_ht']);
        $this->assertEquals(1, $totals['invoice_count']);
    }

    public function test_sales_report_excludes_cancelled_documents(): void
    {
        // Confirmed invoice
        $doc1 = DocumentHeader::factory()->create([
            'user_id'       => $this->admin->id,
            'document_type' => 'InvoiceSale',
            'status'        => 'confirmed',
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc1->id,
            'total_ttc'          => 500,
        ]);

        // Cancelled invoice
        $doc2 = DocumentHeader::factory()->create([
            'user_id'       => $this->admin->id,
            'document_type' => 'InvoiceSale',
            'status'        => 'cancelled',
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc2->id,
            'total_ttc'          => 9999,
        ]);

        $from = now()->subYear()->format('Y-m-d');
        $to   = now()->addDay()->format('Y-m-d');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/reports/sales?from={$from}&to={$to}");

        $this->assertEquals(500, $response->json('totals.revenue_ttc'));
    }

    public function test_sales_report_defaults_to_current_month(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reports/sales');

        $response->assertOk();
        $period = $response->json('period');
        $this->assertEquals(now()->startOfMonth()->toDateString(), $period['from']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  PURCHASES REPORT
    // ═══════════════════════════════════════════════════════════════

    public function test_purchases_report_returns_correct_structure(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reports/purchases?from=2024-01-01&to=2024-12-31');

        $response->assertOk()
            ->assertJsonStructure([
                'period',
                'totals' => ['spending_ttc', 'spending_ht', 'total_tax', 'total_discount', 'invoice_count'],
                'by_type',
                'by_status',
                'top_products',
                'top_suppliers',
                'payments_by_method',
                'daily_spending',
            ]);
    }

    public function test_purchases_report_calculates_totals(): void
    {
        $doc = DocumentHeader::factory()->create([
            'user_id'       => $this->admin->id,
            'document_type' => 'InvoicePurchase',
            'status'        => 'confirmed',
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ht'           => 800,
            'total_tax'          => 160,
            'total_ttc'          => 960,
        ]);

        $from = now()->subYear()->format('Y-m-d');
        $to   = now()->addDay()->format('Y-m-d');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/reports/purchases?from={$from}&to={$to}");

        $this->assertEquals(960, $response->json('totals.spending_ttc'));
    }

    // ═══════════════════════════════════════════════════════════════
    //  STOCK REPORT
    // ═══════════════════════════════════════════════════════════════

    public function test_stock_report_returns_correct_structure(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reports/stock');

        $response->assertOk()
            ->assertJsonStructure([
                'current_stock',
                'total_value' => ['total_qty', 'total_value', 'product_count'],
                'low_stock',
                'out_of_stock',
                'movements_summary',
            ]);
    }

    public function test_stock_report_shows_products_in_stock(): void
    {
        $product   = Product::factory()->create(['p_cost' => 100]);
        $warehouse = Warehouse::factory()->create();

        WarehouseHasStock::factory()->create([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'stockLevel'   => 50,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reports/stock');

        $totalValue = $response->json('total_value');
        $this->assertGreaterThanOrEqual(1, $totalValue['product_count']);
        $this->assertGreaterThan(0, $totalValue['total_qty']);
    }

    public function test_stock_report_filters_by_warehouse(): void
    {
        $wh1 = Warehouse::factory()->create();
        $wh2 = Warehouse::factory()->create();
        $product = Product::factory()->create();

        WarehouseHasStock::factory()->create([
            'product_id'   => $product->id,
            'warehouse_id' => $wh1->id,
            'stockLevel'   => 100,
        ]);
        WarehouseHasStock::factory()->create([
            'product_id'   => $product->id,
            'warehouse_id' => $wh2->id,
            'stockLevel'   => 200,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/reports/stock?warehouse_id={$wh1->id}");

        $response->assertOk();
        $currentStock = $response->json('current_stock');

        foreach ($currentStock as $item) {
            $this->assertEquals($wh1->wh_title, $item['warehouse']);
        }
    }

    public function test_stock_report_identifies_low_stock(): void
    {
        $product   = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        WarehouseHasStock::factory()->create([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'stockLevel'   => 3,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reports/stock');

        $lowStock = $response->json('low_stock');
        $this->assertNotEmpty($lowStock);
    }

    // ═══════════════════════════════════════════════════════════════
    //  PDF ENDPOINTS
    // ═══════════════════════════════════════════════════════════════

    public function test_sales_pdf_returns_pdf(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->get('/api/reports/sales/pdf?from=2024-01-01&to=2024-12-31');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_purchases_pdf_returns_pdf(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->get('/api/reports/purchases/pdf?from=2024-01-01&to=2024-12-31');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_stock_pdf_returns_pdf(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->get('/api/reports/stock/pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }
}
