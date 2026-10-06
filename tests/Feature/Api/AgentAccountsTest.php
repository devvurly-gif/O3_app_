<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\Agents\AgentRegistry;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Un compte utilisateur pour chaque agent : propre à l'agent, sans permission, sans connexion possible, sans jeton.
 */
class AgentAccountsTest extends TestCase
{
    use RefreshTenantDatabase, InteractsWithTenancy;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->fakeTenant();
    }

    private function say(string $text): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated();
    }

    private function custom(bool $active = true): Agent
    {
        return Agent::create(['domain' => 'perso-veille', 'name' => 'Veille stock', 'kind' => 'custom', 'mission' => 'Signale le stock bas.', 'scopes' => ['stock'], 'created_by' => $this->admin->id, 'is_active' => $active, 'default_level' => 'approval']);
    }

    public function test_every_agent_gets_its_own_account_with_the_permissionless_role_and_no_token(): void
    {
        $custom = $this->custom();
        $registry = app(AgentRegistry::class);
        $this->assertCount(8, $registry->agentsWithoutAccount());

        $report = $registry->ensureAllAccounts();

        $this->assertCount(8, $report);
        $this->assertSame([], Agent::whereNull('user_id')->get()->all());
        $this->assertCount(8, array_unique(Agent::pluck('user_id')->all()));              // un compte DISTINCT par agent
        $role = Role::where('name', AgentRegistry::ROLE)->firstOrFail();
        $this->assertSame(0, $role->permissions()->count());                              // aucune permission
        $this->assertTrue($role->is_system);

        $stocks = Agent::where('domain', 'stocks')->firstOrFail();
        $user = User::findOrFail($stocks->user_id);
        $this->assertSame('Agent IA — Stocks', $user->name);
        $this->assertSame('agent-ia.stocks@' . tenant('id') . '.o3app.local', $user->email);
        $this->assertSame($role->id, $user->role_id);
        $this->assertSame('stocks:agent', $stocks->ability);
        $this->assertSame("custom:agent-{$custom->id}", $custom->fresh()->ability);
        $this->assertSame('agent-ia.perso-' . $custom->id . '@' . tenant('id') . '.o3app.local', User::find($custom->fresh()->user_id)->email);
        $this->assertSame(0, PersonalAccessToken::count());                               // aucun jeton émis
    }

    public function test_an_account_follows_its_agents_state_and_existing_accounts_are_never_touched(): void
    {
        $existing = User::factory()->cashier()->create(['email' => 'agent-ia.achats@jadema.o3app.local']);
        $achats = Agent::where('domain', 'achats')->firstOrFail();
        $achats->update(['user_id' => $existing->id, 'ability' => 'achats:import']);
        $before = [$existing->role_id, $existing->email, $existing->password];

        app(AgentRegistry::class)->ensureAllAccounts();

        $existing->refresh();
        $this->assertSame($before, [$existing->role_id, $existing->email, $existing->password]);   // Achats : compte et rôle d'origine inchangés
        $this->assertSame('achats:import', $achats->fresh()->ability);

        // Compte actif ou non selon l'agent : Recouvrement est inactif, Stocks est actif.
        $this->assertFalse((bool) User::find(Agent::where('domain', 'recouvrement')->value('user_id'))->is_active);
        $this->assertTrue((bool) User::find(Agent::where('domain', 'stocks')->value('user_id'))->is_active);

        // Idempotent : une seconde passe ne crée rien et ne change aucun compte.
        $ids = User::pluck('id')->all();
        $this->assertSame([], app(AgentRegistry::class)->ensureAllAccounts());
        $this->assertSame($ids, User::pluck('id')->all());
    }

    public function test_an_agent_account_can_never_log_in_to_the_interface(): void
    {
        $stocks = Agent::where('domain', 'stocks')->firstOrFail();
        $user = app(AgentRegistry::class)->ensureAccount($stocks);
        $user->update(['password' => Hash::make('mot-de-passe-connu')]);               // même si quelqu'un fixait un mot de passe

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'mot-de-passe-connu'])->assertStatus(422);
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $user->id)->count());
    }

    public function test_the_chat_proposes_the_accounts_then_creates_them_at_the_click_only(): void
    {
        $r = $this->say('crée les comptes des agents');

        $event = AgentEvent::where('type', 'comptes_agents')->firstOrFail();
        $body = $r->json('reply.body');
        $this->assertStringContainsString('7 compte(s) à créer', $body);
        $this->assertStringContainsString('Stocks — agent-ia.stocks@', $body);
        $this->assertStringContainsString('Recouvrement — agent-ia.recouvrement@', $body);
        $this->assertStringContainsString('compte inactif (agent inactif)', $body);
        $this->assertStringContainsString("aucune permission", $body);
        $this->assertStringContainsString("Aucun jeton n'est émis", $body);
        $this->assertSame("applique la proposition #{$event->id}", $r->json('reply.suggestions.0.text'));
        $this->assertSame(7, Agent::whereNull('user_id')->count());                      // rien avant le clic
        $this->assertSame(0, Agent::whereNull('user_id')->count() - 7);

        $done = $this->say("applique la proposition #{$event->id}");

        $this->assertStringContainsString('7 compte(s) créé(s)', $done->json('reply.body'));
        $this->assertSame(0, Agent::whereNull('user_id')->count());
        $this->assertSame(7, AgentAction::where('action', 'agent_account_created')->count());
        $this->assertStringContainsString('déjà été traitée', $this->say("applique la proposition #{$event->id}")->json('reply.body'));
        $this->assertStringContainsString('Tous les agents ont déjà leur compte', $this->say('crée les comptes des agents')->json('reply.body'));
    }

    public function test_the_list_shows_each_agents_account_and_a_button_for_the_missing_ones(): void
    {
        app(AgentRegistry::class)->ensureAccount(Agent::where('domain', 'stocks')->firstOrFail());

        $r = $this->say('quels agents ont un compte ?');

        $body = $r->json('reply.body');
        $this->assertStringContainsString('• Stocks — agent-ia.stocks@', $body);
        $this->assertStringContainsString('(rôle agent_ia, actif)', $body);
        $this->assertStringContainsString('• Expédition — aucun compte', $body);
        $this->assertSame('crée les comptes des agents', $r->json('reply.suggestions.0.text'));
    }

    public function test_a_recruited_agent_gets_its_account_immediately_and_deactivating_it_deactivates_the_account(): void
    {
        $agent = $this->custom(false);
        $event = AgentEvent::create(['type' => 'agent_recrutement', 'source' => 'orchestrator', 'status' => 'routed', 'payload' => ['spec' => [
            'name' => 'Veille ventes', 'mission' => 'Surveille les ventes du jour.', 'scopes' => ['ventes'], 'schedule' => null,
        ]]]);

        $this->say("applique la proposition #{$event->id}");

        $new = Agent::where('name', 'Veille ventes')->firstOrFail();
        $this->assertNotNull($new->user_id);
        $account = User::findOrFail($new->user_id);
        $this->assertFalse((bool) $account->is_active);                                 // inactif comme l'agent

        $this->say("active l'agent #{$new->id}");
        $this->assertTrue((bool) $account->fresh()->is_active);

        $this->say("désactive l'agent #{$new->id}");
        $this->assertFalse((bool) $account->fresh()->is_active);
        $this->assertNull($agent->fresh()->user_id);                                    // l'autre agent n'est pas concerné
    }

    public function test_the_artisan_command_exists_and_refuses_an_unknown_tenant(): void
    {
        $this->assertArrayHasKey('agents:create-accounts', Artisan::all());
        $this->assertSame(1, Artisan::call('agents:create-accounts', ['--tenant' => 'tenant-inexistant']));
        $this->assertSame(7, Agent::whereNull('user_id')->count());                                  // rien n'a été créé
    }
}
