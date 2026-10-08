<?php

namespace Tests\Feature\Api;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AgentFoundationSeeder;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * L'option « Agents IA » : éteinte par défaut, allumée par le super-administrateur depuis la gestion des tenants, et
 * réellement ouverte seulement si la formule (Pro ou Business) est payée. Éteinte ou sans droit, elle ferme l'API des
 * agents et retire le module du profil.
 */
class AgentsSwitchTest extends TestCase
{
    use RefreshTenantDatabase, InteractsWithTenancy;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    private function chat(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => 'aide']);
    }

    private function modules(): array
    {
        return $this->actingAs($this->admin, 'sanctum')->getJson('/api/auth/me')->json('active_modules');
    }

    public function test_the_option_is_off_until_the_super_admin_turns_it_on(): void
    {
        $tenant = new Tenant();
        $this->assertFalse($tenant->agentsEnabled());                // jamais touché : éteint
        $tenant->agents_enabled = true;
        $this->assertTrue($tenant->agentsEnabled());
        $tenant->agents_enabled = '0';
        $this->assertFalse($tenant->agentsEnabled());
        $tenant->agents_enabled = false;
        $this->assertFalse($tenant->agentsEnabled());
    }

    public function test_a_paid_pro_or_business_tenant_with_the_option_on_has_the_agents(): void
    {
        foreach (['pro', 'business'] as $plan) {
            $this->fakeTenant(['plan' => $plan, 'status' => TenantStatus::Active, 'agents_enabled' => true]);

            $this->chat()->assertCreated();
            $this->actingAs($this->admin, 'sanctum')->getJson('/api/agents/activite')->assertOk();
            $this->assertContains('agents', $this->modules(), $plan);
        }
    }

    public function test_the_option_off_closes_every_agents_route_and_removes_the_module(): void
    {
        $this->fakeTenant(['plan' => 'business', 'status' => TenantStatus::Active]);       // payé, mais l'option n'a jamais été allumée
        $api = $this->actingAs($this->admin, 'sanctum');

        $this->chat()->assertForbidden()->assertJsonPath('message', 'Les agents IA ne sont pas activés pour ce compte. Contactez O3App pour les activer.');
        $api->getJson('/api/agents/activite')->assertForbidden();
        $api->postJson('/api/agents/ordres', [])->assertForbidden();
        $this->assertNotContains('agents', $this->modules());
        $this->assertDatabaseCount('orchestrator_messages', 0);
    }

    public function test_the_option_on_is_not_enough_without_the_right_plan_and_payment(): void
    {
        $cases = [
            'essentiel payé'      => [['plan' => 'essentiel', 'status' => TenantStatus::Active], 'réservés aux formules Pro et Business'],
            'pro en essai'        => [['plan' => 'pro', 'status' => TenantStatus::Trial], 'réservés aux formules Pro et Business'],
            'business en retard'  => [['plan' => 'business', 'status' => TenantStatus::PastDue], 'réservés aux formules Pro et Business'],
        ];
        foreach ($cases as $label => [$attributes, $message]) {
            $this->fakeTenant($attributes + ['agents_enabled' => true]);

            $r = $this->actingAs($this->admin, 'sanctum')->getJson('/api/agents/activite');          // en lecture : un abonnement en retard est déjà refusé en écriture (402) avant les agents
            $r->assertForbidden();
            $this->assertStringContainsString($message, (string) $r->json('message'), $label);
            $this->assertNotContains('agents', $this->modules(), $label);
        }
    }
}
