<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AgentFoundationSeeder;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * L'interrupteur « Agents IA » de la gestion des tenants : actif par défaut, indépendant de la formule ; désactivé,
 * il ferme l'API des agents et retire le module du profil.
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

    public function test_agents_are_on_unless_the_super_admin_turned_them_off(): void
    {
        $tenant = new Tenant();
        $this->assertTrue($tenant->agentsEnabled());                 // jamais touché : actif
        $tenant->agents_enabled = false;
        $this->assertFalse($tenant->agentsEnabled());
        $tenant->agents_enabled = '0';
        $this->assertFalse($tenant->agentsEnabled());
        $tenant->agents_enabled = true;
        $this->assertTrue($tenant->agentsEnabled());
    }

    public function test_enabled_tenant_keeps_the_agents_api_and_the_profile_module(): void
    {
        $this->fakeTenant();

        $this->chat()->assertCreated();
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/agents/activite')->assertOk();
        $this->assertContains('agents', $this->actingAs($this->admin, 'sanctum')->getJson('/api/auth/me')->json('active_modules'));
    }

    public function test_disabled_tenant_gets_a_403_on_every_agents_route_and_no_module(): void
    {
        $this->fakeTenant(['agents_enabled' => false]);
        $api = $this->actingAs($this->admin, 'sanctum');

        $this->chat()->assertForbidden()->assertJsonPath('message', 'Les agents IA ne sont pas activés pour ce compte. Contactez O3App pour les activer.');
        $api->getJson('/api/agents/activite')->assertForbidden();
        $api->postJson('/api/agents/ordres', [])->assertForbidden();
        $this->assertNotContains('agents', $api->getJson('/api/auth/me')->json('active_modules'));
        $this->assertDatabaseCount('orchestrator_messages', 0);
    }
}
