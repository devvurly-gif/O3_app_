<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\AgentDirective;
use App\Models\AgentEvent;
use App\Models\AgentRoutine;
use App\Models\Setting;
use App\Models\User;
use App\Services\Agents\AgentInterview;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * L'entretien de conception : l'orchestrateur interroge l'administrateur, puis propose un plan complet
 * (fonctions, autorisations, routines, déclencheurs, besoins de développement) créé seulement au clic.
 * Les réponses du modèle sont simulées : aucun appel réel à l'API.
 */
class OrchestratorInterviewTest extends TestCase
{
    use RefreshTenantDatabase, InteractsWithTenancy;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    private function say(string $text): array
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
    }

    private function modelSays(array ...$inputs): void
    {
        Http::swap(new Factory());
        Setting::set('messaging', 'anthropic_api_key', encrypt('sk-test-cle'));
        Setting::set('agents', 'orchestrator_ai_enabled', 'true');
        $sequence = Http::sequence();
        foreach ($inputs as $input) {
            $sequence->pushResponse(Http::response(['content' => [['type' => 'tool_use', 'id' => 't', 'name' => 'interview_step', 'input' => $input]], 'stop_reason' => 'tool_use']));
        }
        Http::fake(['api.anthropic.com/*' => $sequence]);
    }

    private function question(string $q = 'Quel seuil ?', array $options = ['5 pièces', '10 pièces']): array
    {
        return ['reflection' => 'Le stock existe déjà.', 'ready' => false, 'question' => $q, 'options' => $options];
    }

    private function plan(array $over = []): array
    {
        return ['reflection' => 'J\'ai tout ce qu\'il faut.', 'ready' => true, 'spec' => array_merge([
            'name'      => 'Veille ruptures',
            'purpose'   => 'Signaler les produits bientôt en rupture.',
            'functions' => [
                ['label' => 'Lire les stocks bas', 'how' => 'agent_lecture', 'detail' => 'seuil 5'],
                ['label' => 'Commander au fournisseur', 'how' => 'a_developper', 'detail' => 'pas de commande automatique'],
            ],
            'agent'     => ['create' => true, 'name' => 'Veille ruptures', 'mission' => 'Signale les produits dont le stock est bas.', 'scopes' => ['stock', 'inventedscope']],
            'steps'     => ['etat', 'etape_inventee'],
            'schedule'  => ['frequency' => 'daily', 'time' => '08:00'],
            'trigger'   => ['event_type' => 'stock_bas', 'conditions' => ['qty_lte' => 3], 'cooldown_minutes' => 30],
            'dev_needs' => [['title' => 'Commande fournisseur automatique', 'detail' => 'Créer un bon de commande brouillon']],
            'risks'     => ['Le seuil de 3 est celui donné par vous.'],
        ], $over)];
    }

    public function test_the_interview_asks_one_question_at_a_time_with_buttons(): void
    {
        $this->modelSays($this->question());
        $reply = $this->say('discutons de : suivre les produits bientôt en rupture');

        $this->assertStringContainsString('Question 1/' . AgentInterview::MAX_QUESTIONS, $reply['body']);
        $this->assertStringContainsString('Quel seuil ?', $reply['body']);
        $this->assertSame('5 pièces', $reply['suggestions'][0]['text']);
        $this->assertSame(1, AgentEvent::where('type', 'atelier_entretien')->where('status', 'in_progress')->count());
        Http::assertSent(fn (Request $r) => str_contains($r['system'], 'ÉVÉNEMENTS INTERNES') && $r['tool_choice']['name'] === 'interview_step');
    }

    public function test_the_knowledge_pack_lists_existing_agents_and_directives(): void
    {
        Agent::create(['domain' => 'perso-x', 'name' => 'Agent Zéphyr', 'kind' => 'custom', 'mission' => 'Mission zéphyr.', 'scopes' => ['stock'], 'created_by' => $this->admin->id, 'is_active' => true, 'default_level' => 'approval']);
        AgentDirective::create(['body' => 'Toujours parler en dirhams', 'is_active' => true, 'created_by' => $this->admin->id]);
        $this->modelSays($this->question());
        $this->say('discutons de : suivre les ventes du mois');

        Http::assertSent(fn (Request $r) => str_contains($r['system'], 'Agent Zéphyr') && str_contains($r['system'], 'Toujours parler en dirhams') && str_contains($r['system'], 'stock_bas'));
    }

    public function test_free_text_during_an_interview_is_an_answer_not_a_command(): void
    {
        $this->modelSays($this->question(), $this->question('Quel jour ?', ['Lundi']));
        $this->say('discutons de : suivre les produits bientôt en rupture');
        $reply = $this->say('état des agents');   // aurait été une commande hors entretien

        $this->assertStringContainsString('Question 2/', $reply['body']);
        $this->assertSame(2, AgentEvent::where('type', 'atelier_entretien')->first()->payload['questions_asked']);
    }

    public function test_the_plan_is_cleaned_and_authorizations_are_computed(): void
    {
        $this->modelSays($this->question(), $this->plan());
        $this->say('discutons de : suivre les produits bientôt en rupture');
        $reply = $this->say('5 pièces');

        $body = $reply['body'];
        $this->assertStringContainsString('Plan proposé : « Veille ruptures »', $body);
        $this->assertStringContainsString('Lecture — Stock', $body);
        $this->assertStringNotContainsString('inventedscope', $body);
        $this->assertStringNotContainsString('etape_inventee', $body);
        $this->assertStringContainsString('aucune par elle-même', $body);
        $this->assertStringContainsString('Commande fournisseur automatique', $body);

        $proposal = AgentEvent::where('type', 'conception_proposition')->first();
        $this->assertSame('routed', $proposal->status);
        $this->assertSame(['stock'], $proposal->payload['spec']['agent']['scopes']);
        $this->assertSame(['etat'], $proposal->payload['spec']['steps']);
        $this->assertSame("applique la proposition #{$proposal->id}", $reply['suggestions'][0]['text']);
        // Rien n'est créé avant le clic.
        $this->assertSame(0, Agent::where('kind', 'custom')->count());
        $this->assertSame(0, AgentRoutine::count());
    }

    public function test_applying_the_plan_creates_an_inactive_agent_with_account_routines_and_a_dev_request(): void
    {
        $this->modelSays($this->plan());
        $this->say('discutons de : suivre les produits bientôt en rupture');
        $proposal = AgentEvent::where('type', 'conception_proposition')->firstOrFail();
        $before = (int) AgentEvent::max('id');

        $reply = $this->say("applique la proposition #{$proposal->id}");

        $agent = Agent::where('kind', 'custom')->firstOrFail();
        $this->assertFalse($agent->is_active);
        $this->assertNotNull($agent->user_id);                                   // son propre compte
        $this->assertSame(['stock'], $agent->scopes);

        $routines = AgentRoutine::orderBy('id')->get();
        $this->assertCount(2, $routines);
        $this->assertSame(['agent:' . $agent->id, 'etat'], $routines[0]->steps);
        $this->assertSame($agent->id, $routines[0]->agent_id);
        $this->assertFalse($routines[0]->is_active);                             // attend l'activation de l'agent
        $this->assertTrue($routines[1]->isEventDriven());
        $this->assertSame('stock_bas', $routines[1]->trigger['event_type']);
        $this->assertSame(3, $routines[1]->trigger['conditions']['qty_lte']);
        $this->assertGreaterThanOrEqual($before, $routines[1]->last_event_id);

        $dev = AgentEvent::where('type', 'demande_developpement')->firstOrFail();
        $this->assertStringContainsString('Commande fournisseur automatique', $dev->payload['brief']);

        $this->assertStringContainsString('recruté (inactif)', $reply['body']);
        $this->assertSame('done', $proposal->fresh()->status);
        $this->assertNull(AgentEvent::where('type', 'atelier_entretien')->where('status', 'in_progress')->first());   // l'entretien est clos
        $this->assertSame("active l'agent #{$agent->id}", $reply['suggestions'][0]['text']);

        // Valider deux fois ne crée rien de plus.
        $this->say("applique la proposition #{$proposal->id}");
        $this->assertSame(1, Agent::where('kind', 'custom')->count());
        $this->assertSame(2, AgentRoutine::count());
    }

    public function test_a_plan_without_an_agent_creates_active_routines_of_known_steps(): void
    {
        $this->modelSays($this->plan(['agent' => null, 'trigger' => null, 'dev_needs' => [], 'steps' => ['encaissements']]));
        $this->say('discutons de : contrôler les encaissements chaque matin');
        $proposal = AgentEvent::where('type', 'conception_proposition')->firstOrFail();

        $this->say("applique la proposition #{$proposal->id}");

        $this->assertSame(0, Agent::where('kind', 'custom')->count());
        $routine = AgentRoutine::firstOrFail();
        $this->assertTrue($routine->is_active);
        $this->assertNotNull($routine->next_run_at);
    }

    public function test_ignoring_the_plan_closes_the_interview_and_creates_nothing(): void
    {
        $this->modelSays($this->plan());
        $this->say('discutons de : suivre les produits bientôt en rupture');
        $proposal = AgentEvent::where('type', 'conception_proposition')->firstOrFail();

        $this->say("ignore la proposition #{$proposal->id}");

        $this->assertSame('rejected', $proposal->fresh()->status);
        $this->assertSame(0, Agent::where('kind', 'custom')->count());
        $this->assertNull(AgentEvent::where('type', 'atelier_entretien')->where('status', 'in_progress')->first());
    }

    public function test_the_admin_can_cancel_the_interview(): void
    {
        $this->modelSays($this->question());
        $this->say('discutons de : suivre les produits bientôt en rupture');
        $reply = $this->say("annule l'entretien");

        $this->assertStringContainsString('annulé', $reply['body']);
        $this->assertNull(AgentEvent::where('type', 'atelier_entretien')->where('status', 'in_progress')->first());
        // Hors entretien, une commande ordinaire redevient une commande.
        $this->assertStringNotContainsString('Question', $this->say('état des agents')['body']);
    }

    public function test_finish_forces_the_final_plan_and_the_question_cap_does_too(): void
    {
        $this->modelSays($this->question(), $this->plan());
        $this->say('discutons de : suivre les produits bientôt en rupture');
        $reply = $this->say('termine');

        $this->assertStringContainsString('Plan proposé', $reply['body']);
        Http::assertSent(fn (Request $r) => str_contains($r['messages'][0]['content'], 'Conclus maintenant'));
    }

    public function test_an_unusable_final_plan_does_not_invent_one(): void
    {
        $this->modelSays($this->question(), ['reflection' => 'Hmm.', 'ready' => true, 'spec' => ['name' => 'Rien', 'agent' => ['create' => true, 'name' => 'Ab', 'mission' => 'x', 'scopes' => ['faux']]]]);
        $this->say('discutons de : quelque chose de vague');
        $reply = $this->say('termine');

        $this->assertStringContainsString("pas pu établir un plan exploitable", $reply['body']);
        $this->assertSame(0, AgentEvent::where('type', 'conception_proposition')->count());
    }

    public function test_an_api_failure_keeps_the_interview_open(): void
    {
        $this->modelSays($this->question());
        $this->say('discutons de : suivre les produits bientôt en rupture');
        Http::swap(new Factory());
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $reply = $this->say('5 pièces');

        $this->assertTrue($reply['error']);
        $this->assertStringContainsString('reste ouvert', $reply['body']);
        $this->assertSame(1, AgentEvent::where('type', 'atelier_entretien')->where('status', 'in_progress')->count());
    }

    public function test_without_the_ai_the_interview_explains_instead_of_starting(): void
    {
        Setting::set('agents', 'orchestrator_ai_enabled', 'false');
        $reply = $this->say('discutons de : suivre les produits bientôt en rupture');

        $this->assertStringContainsString('compréhension avancée', $reply['body']);
        $this->assertSame(0, AgentEvent::where('type', 'atelier_entretien')->count());
    }

    public function test_the_daily_cap_stops_the_interview_politely(): void
    {
        $this->modelSays($this->question());
        Cache::put('agent_interview:central:' . now()->format('Y-m-d'), AgentInterview::DAILY_CAP, now()->endOfDay());
        $reply = $this->say('discutons de : suivre les produits bientôt en rupture');

        $this->assertStringContainsString('plafond', $reply['body']);
        Http::assertNothingSent();
    }

    public function test_a_stale_interview_is_dropped(): void
    {
        $this->modelSays($this->question());
        $this->say('discutons de : suivre les produits bientôt en rupture');
        AgentEvent::where('type', 'atelier_entretien')->update(['updated_at' => now()->subHours(13)]);

        $reply = $this->say('bonjour');

        $this->assertStringNotContainsString('Question', $reply['body']);
        $this->assertSame(0, AgentEvent::where('type', 'atelier_entretien')->where('status', 'in_progress')->count());
    }

    public function test_id_commands_pass_through_during_an_interview(): void
    {
        $this->modelSays($this->question());
        $this->say('discutons de : suivre les produits bientôt en rupture');
        $reply = $this->say('la demande #999 est faite');

        $this->assertStringContainsString('Je ne trouve pas de demande', $reply['body']);
        $this->assertSame(1, AgentEvent::where('type', 'atelier_entretien')->where('status', 'in_progress')->count());
    }
}
