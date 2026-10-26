<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Services\Agents\AgentRegistry;
use App\Services\Agents\EventRouter;
use Database\Seeders\AgentFoundationSeeder;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GET /api/agents/activite : lecture seule, réservée à ceux qui gèrent les réglages.
 */
class AgentActivityTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
    }

    private function event(array $attrs): AgentEvent
    {
        $event = AgentEvent::create($attrs + ['status' => AgentEvent::STATUS_NEW]);

        return app(EventRouter::class)->route($event);
    }

    public function test_admin_sees_summary_agents_and_routed_events(): void
    {
        Setting::set('agents', 'router_enabled', 'true');
        $client = ThirdPartner::factory()->customer()->create(['tp_title' => 'Quincaillerie Atlas']);
        $this->event(['source' => 'whatsapp', 'payload' => ['text' => 'je veux un devis'], 'entities' => ['third_partner_id' => $client->id]]);
        $this->event(['source' => 'email', 'payload' => ['text' => 'bonjour']]);

        $r = $this->actingAs(User::factory()->admin()->create(), 'sanctum')->getJson('/api/agents/activite')->assertOk();

        $this->assertSame(2, $r->json('summary.total'));
        $this->assertSame(1, $r->json('summary.by_status.routed'));
        $this->assertSame(1, $r->json('summary.by_status.to_sort'));
        $this->assertSame(1, $r->json('summary.open_cases'));
        $this->assertTrue($r->json('summary.router_on'));
        $this->assertCount(7, $r->json('agents'));

        // Le plus récent d'abord ; le routé porte son agent, son client et son texte.
        $this->assertSame('to_sort', $r->json('events.0.status'));
        $routed = collect($r->json('events'))->firstWhere('type', 'demande_devis');
        $this->assertSame('ventes', $routed['agent']['domain']);
        $this->assertSame('Quincaillerie Atlas', $routed['client']['title']);
        $this->assertSame('je veux un devis', $routed['text']);

        $ventes = collect($r->json('agents'))->firstWhere('domain', 'ventes');
        $this->assertSame(1, $ventes['events_count']);
    }

    public function test_each_agent_carries_its_tasks_and_what_is_missing(): void
    {
        $r = $this->actingAs(User::factory()->admin()->create(), 'sanctum')->getJson('/api/agents/activite')->assertOk();

        $marketing = collect($r->json('agents'))->firstWhere('domain', 'marketing');
        $this->assertStringContainsString('publie les produits sur le website', collect($marketing['tasks'])->pluck('how')->implode(' '));
        $this->assertNotEmpty($marketing['missing']);

        $expedition = collect($r->json('agents'))->firstWhere('domain', 'expedition');
        $this->assertSame([], $expedition['tasks']);
    }

    public function test_an_admin_toggles_an_agent_directly_and_its_account_follows(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = Agent::where('domain', 'marketing')->firstOrFail();
        $this->assertFalse($agent->is_active);
        $user = app(AgentRegistry::class)->ensureAccount($agent);
        $this->assertFalse((bool) $user->fresh()->is_active);

        $this->actingAs($admin, 'sanctum')->putJson("/api/agents/{$agent->id}/etat", ['is_active' => true])
            ->assertOk()->assertJson(['domain' => 'marketing', 'is_active' => true]);
        $this->assertTrue($agent->fresh()->is_active);
        $this->assertTrue((bool) $user->fresh()->is_active);   // le compte suit l'agent
        $this->assertSame(1, AgentAction::where('agent_id', $agent->id)->where('action', 'agent_activated')->count());

        $this->actingAs($admin, 'sanctum')->putJson("/api/agents/{$agent->id}/etat", ['is_active' => false])->assertOk();
        $this->assertFalse((bool) $user->fresh()->is_active);
        $this->assertSame(1, AgentAction::where('action', 'agent_deactivated')->count());

        $this->actingAs($admin, 'sanctum')->putJson("/api/agents/{$agent->id}/etat", [])->assertStatus(422);
    }

    public function test_an_admin_creates_the_agent_account_once_without_a_token(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = Agent::where('domain', 'achats')->firstOrFail();
        $this->assertNull($agent->user_id);

        $first = $this->actingAs($admin, 'sanctum')->postJson("/api/agents/{$agent->id}/compte")->assertCreated();
        $this->assertTrue($first->json('created'));
        $agent->refresh();
        $this->assertNotNull($agent->user_id);
        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $agent->user_id)->count());   // aucun jeton émis

        $again = $this->actingAs($admin, 'sanctum')->postJson("/api/agents/{$agent->id}/compte")->assertOk();
        $this->assertFalse($again->json('created'));
        $this->assertSame(1, AgentAction::where('agent_id', $agent->id)->where('action', 'agent_account_created')->count());
        $this->assertSame($agent->user_id, $agent->fresh()->user_id);

        // L'état de l'écran reflète le compte.
        $row = collect($this->actingAs($admin, 'sanctum')->getJson('/api/agents/activite')->json('agents'))->firstWhere('id', $agent->id);
        $this->assertNotNull($row['account']);
    }

    public function test_agent_management_is_closed_to_non_admins_and_restricted_tokens(): void
    {
        $agent = Agent::where('domain', 'ventes')->firstOrFail();
        foreach (['manager', 'cashier', 'warehouse'] as $role) {
            $user = User::factory()->{$role}()->create();
            $this->actingAs($user, 'sanctum')->putJson("/api/agents/{$agent->id}/etat", ['is_active' => true])->assertForbidden();
            $this->actingAs($user, 'sanctum')->postJson("/api/agents/{$agent->id}/compte")->assertForbidden();
        }

        $admin = User::factory()->admin()->create();
        $plain = $admin->createToken('agent', ['achats:import'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$plain}")->putJson("/api/agents/{$agent->id}/etat", ['is_active' => true])->assertForbidden();

        $this->assertNull($agent->fresh()->user_id);
        $this->assertSame((bool) Agent::where('domain', 'ventes')->value('is_active'), $agent->fresh()->is_active);
    }

    public function test_filters_narrow_the_events_but_not_the_summary(): void
    {
        $this->event(['source' => 'whatsapp', 'payload' => ['text' => 'je veux un devis']]);
        $this->event(['source' => 'email', 'payload' => ['text' => 'bonjour']]);
        $admin = User::factory()->admin()->create();

        $byStatus = $this->actingAs($admin, 'sanctum')->getJson('/api/agents/activite?status=to_sort')->assertOk();
        $this->assertCount(1, $byStatus->json('events'));
        $this->assertSame(2, $byStatus->json('summary.total'));

        $byAgent = $this->actingAs($admin, 'sanctum')->getJson('/api/agents/activite?agent=ventes')->assertOk();
        $this->assertSame(['demande_devis'], collect($byAgent->json('events'))->pluck('type')->all());
    }

    public function test_non_admin_roles_are_refused(): void
    {
        foreach (['manager', 'cashier', 'warehouse'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create(), 'sanctum')
                ->getJson('/api/agents/activite')
                ->assertForbidden();
        }
    }

    public function test_agent_tokens_with_restricted_abilities_are_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $plain = $admin->createToken('agent', ['achats:import'])->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$plain}")->getJson('/api/agents/activite')->assertForbidden();
    }

    public function test_guests_are_refused(): void
    {
        $this->getJson('/api/agents/activite')->assertUnauthorized();
    }
}
