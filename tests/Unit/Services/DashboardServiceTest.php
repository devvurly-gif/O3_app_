<?php

namespace Tests\Unit\Services;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DashboardService::class);
    }

    public function test_get_kpis_returns_expected_structure(): void
    {
        $kpis = $this->service->getKpis();

        $this->assertArrayHasKey('cards', $kpis);
        $this->assertArrayHasKey('revenue_chart', $kpis);
        $this->assertArrayHasKey('top_products', $kpis);
        $this->assertArrayHasKey('low_stock', $kpis);
        $this->assertArrayHasKey('recent_documents', $kpis);
        $this->assertArrayHasKey('pending_orders', $kpis);
        $this->assertArrayHasKey('top_clients', $kpis);
    }

    public function test_cards_count_active_products(): void
    {
        Product::factory()->count(5)->create(['p_status' => true]);
        Product::factory()->count(2)->create(['p_status' => false]);

        $kpis  = $this->service->getKpis();
        $cards = collect($kpis['cards']);

        $productsCard = $cards->firstWhere('key', 'products');
        $this->assertEquals(5, $productsCard['value']);
    }

    public function test_cards_count_active_clients_and_suppliers(): void
    {
        $existingClients   = ThirdPartner::whereIn('tp_Role', ['customer', 'both'])->where('tp_status', true)->count();
        $existingSuppliers = ThirdPartner::whereIn('tp_Role', ['supplier', 'both'])->where('tp_status', true)->count();

        ThirdPartner::factory()->customer()->count(3)->create(['tp_status' => true]);
        ThirdPartner::factory()->supplier()->count(2)->create(['tp_status' => true]);
        ThirdPartner::factory()->customer()->create(['tp_status' => false]);

        $kpis  = $this->service->getKpis();
        $cards = collect($kpis['cards']);

        $this->assertEquals($existingClients + 3, $cards->firstWhere('key', 'clients')['value']);
        $this->assertEquals($existingSuppliers + 2, $cards->firstWhere('key', 'suppliers')['value']);
    }

    public function test_revenue_chart_returns_6_months(): void
    {
        $kpis = $this->service->getKpis();

        $this->assertCount(6, $kpis['revenue_chart']);
        $this->assertArrayHasKey('month', $kpis['revenue_chart'][0]);
        $this->assertArrayHasKey('total', $kpis['revenue_chart'][0]);
    }

    public function test_ca_month_sums_invoice_footers(): void
    {
        $user = User::factory()->create();

        $invoice = DocumentHeader::factory()->invoice()->create([
            'user_id'    => $user->id,
            'status'     => 'confirmed',
            'created_at' => now(),
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $invoice->id,
            'total_ttc'          => 1500.00,
        ]);

        $cancelledInvoice = DocumentHeader::factory()->invoice()->create([
            'user_id'    => $user->id,
            'status'     => 'cancelled',
            'created_at' => now(),
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $cancelledInvoice->id,
            'total_ttc'          => 999.00,
        ]);

        $kpis  = $this->service->getKpis();
        $cards = collect($kpis['cards']);
        $ca    = $cards->firstWhere('key', 'ca_month');

        $this->assertEquals(1500.00, $ca['value']);
    }

    public function test_low_stock_returns_low_items(): void
    {
        WarehouseHasStock::factory()->create(['stockLevel' => 3]);
        WarehouseHasStock::factory()->create(['stockLevel' => 100]);

        $kpis = $this->service->getKpis();

        $this->assertCount(1, $kpis['low_stock']);
        $this->assertEquals(3, $kpis['low_stock'][0]['stockLevel']);
    }

    public function test_recent_documents_returns_latest(): void
    {
        $user = User::factory()->create();
        DocumentHeader::factory()->count(3)->create(['user_id' => $user->id]);

        $kpis = $this->service->getKpis();

        $this->assertCount(3, $kpis['recent_documents']);
    }

    public function test_pending_orders_only_includes_unpaid_invoices(): void
    {
        $user = User::factory()->create();

        DocumentHeader::factory()->invoice()->create([
            'user_id' => $user->id,
            'status'  => 'confirmed',
        ]);
        DocumentHeader::factory()->invoice()->create([
            'user_id' => $user->id,
            'status'  => 'paid',
        ]);
        DocumentHeader::factory()->quote()->create([
            'user_id' => $user->id,
            'status'  => 'confirmed',
        ]);

        $kpis = $this->service->getKpis();

        $this->assertCount(1, $kpis['pending_orders']);
    }
}
