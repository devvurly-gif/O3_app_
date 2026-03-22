<?php

namespace Tests\Feature\Api;

use App\Models\DocumentHeader;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $cashier;
    private User $warehouseUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin         = User::factory()->admin()->create();
        $this->manager       = User::factory()->manager()->create();
        $this->cashier       = User::factory()->cashier()->create();
        $this->warehouseUser = User::factory()->warehouse()->create();
    }

    // ═══════════════════════════════════════════════════════════════
    //  ADMIN-ONLY ENDPOINTS
    // ═══════════════════════════════════════════════════════════════

    public function test_admin_can_list_users(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/users')
            ->assertOk();
    }

    public function test_manager_cannot_list_users(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_cashier_cannot_list_users(): void
    {
        $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_admin_can_manage_settings(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/settings', [
                'domain'   => 'company',
                'settings' => ['name' => 'Test Company'],
            ])
            ->assertOk();
    }

    public function test_manager_cannot_manage_settings(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/settings', [
                'domain'   => 'company',
                'settings' => ['name' => 'Test'],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_manage_roles(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/roles')
            ->assertOk();
    }

    public function test_cashier_cannot_manage_roles(): void
    {
        $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/roles')
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════
    //  CATALOGUE WRITE (admin, manager)
    // ═══════════════════════════════════════════════════════════════

    public function test_manager_can_create_product(): void
    {
        $category = \App\Models\Category::factory()->create();

        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/products', [
                'p_title'         => 'Test Product',
                'p_sku'           => 'TST-001',
                'p_purchasePrice' => 10,
                'p_salePrice'     => 20,
                'p_status'        => true,
                'category_id'     => $category->id,
            ])
            ->assertCreated();
    }

    public function test_cashier_cannot_create_product(): void
    {
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/products', [
                'p_title'  => 'Test Product',
                'p_sku'    => 'TST-002',
                'p_status' => true,
            ])
            ->assertForbidden();
    }

    public function test_warehouse_cannot_create_product(): void
    {
        $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/products', [
                'p_title' => 'Test',
                'p_sku'   => 'TST-003',
            ])
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════
    //  READ ACCESS (all authenticated)
    // ═══════════════════════════════════════════════════════════════

    public function test_all_roles_can_read_products(): void
    {
        Product::factory()->create();

        foreach ([$this->admin, $this->manager, $this->cashier, $this->warehouseUser] as $user) {
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/products')
                ->assertOk();
        }
    }

    public function test_all_roles_can_read_documents(): void
    {
        DocumentHeader::factory()->create(['user_id' => $this->admin->id]);

        foreach ([$this->admin, $this->manager, $this->cashier, $this->warehouseUser] as $user) {
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/documents')
                ->assertOk();
        }
    }

    public function test_all_roles_can_read_warehouses(): void
    {
        Warehouse::factory()->create();

        foreach ([$this->admin, $this->manager, $this->cashier, $this->warehouseUser] as $user) {
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/warehouses')
                ->assertOk();
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  DOCUMENT WRITE (admin, manager, cashier)
    // ═══════════════════════════════════════════════════════════════

    public function test_cashier_can_create_document(): void
    {
        $incrementor = \App\Models\DocumentIncrementor::factory()->create();
        $partner     = \App\Models\ThirdPartner::factory()->create();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/documents', [
                'document_incrementor_id' => $incrementor->id,
                'document_type'           => 'InvoiceSale',
                'document_title'          => 'Facture Cashier',
                'thirdPartner_id'         => $partner->id,
                'company_role'            => 'customer',
                'status'                  => 'draft',
                'issued_at'               => now()->format('Y-m-d'),
            ])
            ->assertCreated();
    }

    public function test_warehouse_cannot_create_document(): void
    {
        $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/documents', [
                'document_type'  => 'InvoiceSale',
                'document_title' => 'Should Fail',
            ])
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════
    //  STOCK WRITE (admin, manager, warehouse)
    // ═══════════════════════════════════════════════════════════════

    public function test_warehouse_can_do_stock_entry(): void
    {
        $product   = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/stock/entree', [
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity'     => 10,
            ])
            ->assertCreated();
    }

    public function test_cashier_cannot_do_stock_entry(): void
    {
        $product   = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/stock/entree', [
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity'     => 10,
            ])
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════
    //  UNAUTHENTICATED ACCESS
    // ═══════════════════════════════════════════════════════════════

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
        $this->getJson('/api/documents')->assertUnauthorized();
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════════
    //  REPORTS (admin, manager only)
    // ═══════════════════════════════════════════════════════════════

    public function test_admin_can_access_reports(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reports/sales?from=2024-01-01&to=2024-12-31')
            ->assertOk();
    }

    public function test_manager_can_access_reports(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/reports/stock')
            ->assertOk();
    }

    public function test_cashier_cannot_access_reports(): void
    {
        $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/reports/sales')
            ->assertForbidden();
    }

    public function test_warehouse_cannot_access_reports(): void
    {
        $this->actingAs($this->warehouseUser, 'sanctum')
            ->getJson('/api/reports/purchases')
            ->assertForbidden();
    }
}
