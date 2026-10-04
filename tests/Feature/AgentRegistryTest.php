<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Services\Agents\AgentRegistry;
use Database\Seeders\AgentFoundationSeeder;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Les comptes techniques Sanctum des agents IA sont rattachés à la table agents.
 */
class AgentRegistryTest extends TestCase
{
    use RefreshTenantDatabase;

    private function serviceAccount(string $domain): User
    {
        return User::factory()->create(['email' => AgentRegistry::ACCOUNTS[$domain]['email']]);
    }

    public function test_register_creates_the_agent_linked_to_its_account(): void
    {
        $user = $this->serviceAccount('achats');

        $agent = app(AgentRegistry::class)->register('achats', $user, 'achats:import', 'Achats & catalogue');

        $this->assertSame($user->id, $agent->user_id);
        $this->assertSame('achats:import', $agent->ability);
        $this->assertTrue($agent->is_active);
    }

    public function test_register_keeps_manual_name_and_active_flag_of_an_existing_agent(): void
    {
        $this->seed(AgentFoundationSeeder::class);
        Agent::where('domain', 'ventes')->update(['name' => 'Nom choisi', 'is_active' => false]);
        $user = $this->serviceAccount('ventes');

        app(AgentRegistry::class)->register('ventes', $user, 'ventes:whatsapp-import', 'Ventes & CRM');

        $agent = Agent::where('domain', 'ventes')->sole();
        $this->assertSame($user->id, $agent->user_id);
        $this->assertSame('Nom choisi', $agent->name);
        $this->assertFalse($agent->is_active);
    }

    public function test_link_existing_accounts_reports_found_and_missing_and_is_idempotent(): void
    {
        $achats = $this->serviceAccount('achats');
        $registry = app(AgentRegistry::class);

        $report = $registry->linkExistingAccounts();
        $registry->linkExistingAccounts();

        $this->assertSame(['achats' => 'lié', 'ventes' => 'compte introuvable'], $report);
        $this->assertSame($achats->id, Agent::where('domain', 'achats')->sole()->user_id);
        $this->assertSame(1, Agent::count());
    }

    public function test_linking_does_not_issue_or_touch_any_token(): void
    {
        $achats = $this->serviceAccount('achats');
        $achats->createToken('agent-saisie', ['achats:import']);

        app(AgentRegistry::class)->linkExistingAccounts();

        $this->assertSame(1, $achats->tokens()->count());
    }
}
