<?php

namespace Tests\Feature\Api;

use App\Models\AgentEvent;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
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
