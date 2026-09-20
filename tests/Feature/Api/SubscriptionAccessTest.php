<?php

namespace Tests\Feature\Api;

use App\Enums\TenantStatus;
use App\Models\Brand;
use App\Models\User;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Le filtre qui rend l'abonnement réel.
 *
 * `is_active` et `trial_ends_at` existaient depuis l'origine sans qu'aucun
 * code ne les lise : un compte non vérifié, désactivé ou dont l'essai était
 * terminé depuis des mois continuait de fonctionner normalement. Ces tests
 * sont ce qui empêche ce trou de se rouvrir.
 */
class SubscriptionAccessTest extends TestCase
{
    use RefreshTenantDatabase;
    use InteractsWithTenancy;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ── Abonnement en cours ──────────────────────────────────────

    public function test_a_tenant_on_trial_can_read_and_write(): void
    {
        $this->fakeTenant();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/brands')
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/brands', ['br_title' => 'Marque essai'])
            ->assertCreated();
    }

    // ── Échéance dépassée : lecture seule ────────────────────────

    public function test_an_expired_tenant_can_still_read_its_own_data(): void
    {
        $this->fakeTenant([
            'status'               => TenantStatus::PastDue,
            'subscription_ends_at' => now()->subDays(3),
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/brands')
            ->assertOk();
    }

    public function test_an_expired_tenant_cannot_write(): void
    {
        $this->fakeTenant([
            'status'               => TenantStatus::PastDue,
            'subscription_ends_at' => now()->subDays(3),
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/brands', ['br_title' => 'Marque impayée'])
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_inactive')
            ->assertJsonPath('subscription.status', 'past_due');
    }

    public function test_an_expired_tenant_cannot_delete_either(): void
    {
        $brand = Brand::factory()->create();

        $this->fakeTenant([
            'status'               => TenantStatus::PastDue,
            'subscription_ends_at' => now()->subDays(3),
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/brands/{$brand->id}")
            ->assertStatus(402);

        $this->assertDatabaseHas('brands', ['id' => $brand->id]);
    }

    // ── Suspension et désactivation ──────────────────────────────

    public function test_a_suspended_tenant_is_refused_even_in_read(): void
    {
        $this->fakeTenant([
            'status'               => TenantStatus::Suspended,
            'subscription_ends_at' => now()->subMonths(2),
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/brands')
            ->assertForbidden();
    }

    public function test_a_manually_deactivated_tenant_is_refused(): void
    {
        $this->fakeTenant(['is_active' => false]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/brands')
            ->assertForbidden();
    }

    public function test_an_unverified_tenant_is_refused(): void
    {
        $this->fakeTenant([
            'status'    => TenantStatus::Pending,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/brands')
            ->assertForbidden();
    }

    // ── Porte de sortie ──────────────────────────────────────────

    public function test_a_suspended_tenant_can_still_reach_its_subscription_page(): void
    {
        // Sans cette exception, un client suspendu n'aurait aucun moyen de
        // régulariser : il serait refusé sur la page même qui lui permet de
        // choisir une formule.
        $this->fakeTenant([
            'status'               => TenantStatus::Suspended,
            'subscription_ends_at' => now()->subMonths(2),
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('status', 'suspended')
            ->assertJsonStructure(['plans', 'days_left', 'can_write']);
    }

    public function test_a_suspended_tenant_can_request_a_plan(): void
    {
        $this->fakeTenant([
            'status'               => TenantStatus::Suspended,
            'subscription_ends_at' => now()->subMonths(2),
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/subscription/request', ['plan' => 'pro'])
            ->assertStatus(202);
    }

    public function test_a_non_admin_cannot_subscribe_on_behalf_of_the_company(): void
    {
        $this->fakeTenant();

        $this->actingAs(User::factory()->cashier()->create(), 'sanctum')
            ->postJson('/api/subscription/request', ['plan' => 'pro'])
            ->assertForbidden();
    }

    public function test_an_unknown_plan_is_rejected(): void
    {
        $this->fakeTenant();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/subscription/request', ['plan' => 'platine'])
            ->assertStatus(422);
    }
}
