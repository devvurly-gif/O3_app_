<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * p_purchasePrice / p_cost are gated by the `products.view_cost` permission.
 * Hiding the columns front-end only would be cosmetic — these tests pin the
 * fact that the values never leave the API for a role that lacks it.
 */
class ProductCostVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * database/migrations/ has drifted: the last 11 migrations (variants
     * among them) only landed in database/migrations/tenant/, and
     * Product::$total_stock queries product_variants on every serialisation.
     * Point the test DB at the tenant path — the schema tenants actually run.
     */
    protected function migrateFreshUsing(): array
    {
        return [
            '--path'     => 'database/migrations/tenant',
            '--realpath' => false,
            '--drop-views' => false,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->value('id'),
        ]);
    }

    public function test_index_hides_cost_fields_from_a_role_without_the_permission(): void
    {
        Product::factory()->create(['p_purchasePrice' => 80, 'p_cost' => 85, 'p_salePrice' => 120]);

        $row = $this->actingAs($this->userWithRole('cashier'), 'sanctum')
            ->getJson('/api/products')
            ->assertOk()
            ->json('data.0');

        $this->assertArrayNotHasKey('p_purchasePrice', $row);
        $this->assertArrayNotHasKey('p_cost', $row);
        // The sale price is not a cost field and must survive.
        $this->assertArrayHasKey('p_salePrice', $row);
    }

    public function test_index_keeps_cost_fields_for_a_role_holding_the_permission(): void
    {
        Product::factory()->create(['p_purchasePrice' => 80, 'p_cost' => 85]);

        $row = $this->actingAs($this->userWithRole('manager'), 'sanctum')
            ->getJson('/api/products')
            ->assertOk()
            ->json('data.0');

        $this->assertEquals(80, (float) $row['p_purchasePrice']);
        $this->assertEquals(85, (float) $row['p_cost']);
    }

    public function test_show_hides_cost_fields_from_a_role_without_the_permission(): void
    {
        $product = Product::factory()->create(['p_purchasePrice' => 80, 'p_cost' => 85]);

        $payload = $this->actingAs($this->userWithRole('cashier'), 'sanctum')
            ->getJson('/api/products/' . $product->id)
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('p_purchasePrice', $payload);
        $this->assertArrayNotHasKey('p_cost', $payload);
    }

    public function test_admin_sees_cost_fields_even_before_the_permission_row_is_synced(): void
    {
        // Mirrors a tenant that hasn't run `tenants:sync-permissions` yet:
        // the permission exists nowhere, and the admin must stay whole.
        Role::where('name', 'admin')->first()->permissions()->detach();
        Product::factory()->create(['p_purchasePrice' => 80, 'p_cost' => 85]);

        $payload = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/products')
            ->assertOk()
            ->json('data.0');

        $this->assertArrayHasKey('p_purchasePrice', $payload);
        $this->assertArrayHasKey('p_cost', $payload);
    }
}
