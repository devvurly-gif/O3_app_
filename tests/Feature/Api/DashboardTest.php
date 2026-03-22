<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_kpis(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/dashboard');

        $response->assertOk()
                 ->assertJsonStructure([
                     'cards',
                     'revenue_chart',
                     'top_products',
                     'low_stock',
                     'recent_documents',
                     'pending_orders',
                     'top_clients',
                 ]);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/dashboard')
             ->assertUnauthorized();
    }

    public function test_dashboard_accessible_by_any_role(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->getJson('/api/dashboard')
             ->assertOk();
    }

    public function test_dashboard_cards_contain_expected_keys(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/dashboard');

        $cards = collect($response->json('cards'));
        $keys  = $cards->pluck('key')->toArray();

        $this->assertContains('ca_month', $keys);
        $this->assertContains('payments_month', $keys);
        $this->assertContains('invoices_month', $keys);
        $this->assertContains('outstanding', $keys);
        $this->assertContains('products', $keys);
        $this->assertContains('clients', $keys);
        $this->assertContains('suppliers', $keys);
    }

    public function test_dashboard_revenue_chart_has_6_months(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/dashboard');

        $this->assertCount(6, $response->json('revenue_chart'));
    }
}
