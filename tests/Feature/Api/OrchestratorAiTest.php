<?php

namespace Tests\Feature\Api;

use App\Models\AgentEvent;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use App\Services\Agents\OrchestratorInterpreter;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Compréhension de la phrase libre par un modèle de langage, en renfort des
 * règles : il range la phrase dans une demande connue, n'exécute rien, ne voit
 * aucune donnée de l'entreprise, et un ordre n'est jamais lancé sans confirmation.
 * Aucun appel réel à l'API : les réponses sont simulées.
 */
class OrchestratorAiTest extends TestCase
{
    use RefreshTenantDatabase;

    private const FREE_STATUS = "qu'est-ce qui se passe chez nous en ce moment ?";
    private const FREE_ORDER = "il faudrait recompter tout ce qu'il y a au magasin centre, seulement ce qui cloche";

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        Warehouse::factory()->create(['wh_title' => 'Dépôt Principal', 'wh_status' => true]);
        Warehouse::factory()->create(['wh_title' => 'Magasin Centre', 'wh_status' => true]);
    }

    private function enableAi(): void
    {
        Setting::set('messaging', 'anthropic_api_key', encrypt('sk-test-cle'));
        Setting::set('agents', 'orchestrator_ai_enabled', 'true');
    }

    private function aiReplies(array $input): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'route_request', 'input' => $input]]])]);
    }

    private function say(string $text): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated();
    }

    // ── Désactivé : le comportement d'avant ──────────────────────────

    public function test_it_is_off_by_default_and_calls_nothing(): void
    {
        Http::fake();

        $r = $this->say(self::FREE_STATUS);

        $this->assertStringContainsString("Je n'ai pas compris", $r->json('reply.body'));
        $this->assertFalse($r->json('reply.ai'));
        Http::assertNothingSent();
    }

    public function test_enabled_without_an_api_key_does_nothing(): void
    {
        Setting::set('agents', 'orchestrator_ai_enabled', 'true');
        Http::fake();

        $this->assertStringContainsString("Je n'ai pas compris", $this->say(self::FREE_STATUS)->json('reply.body'));
        Http::assertNothingSent();
    }

    public function test_the_rules_keep_priority_so_a_known_phrase_never_calls_the_model(): void
    {
        $this->enableAi();
        Http::fake();

        $this->assertStringContainsString('Situation du', $this->say('état des agents')->json('reply.body'));
        Http::assertNothingSent();
    }

    // ── Lectures : traitées directement ──────────────────────────────

    public function test_a_free_question_is_classified_and_answered_by_the_usual_handler(): void
    {
        $this->enableAi();
        $this->aiReplies(['intent' => 'etat']);

        $r = $this->say(self::FREE_STATUS);

        $this->assertStringContainsString('Situation du', $r->json('reply.body'));
        $this->assertTrue($r->json('reply.ai'));
    }

    public function test_the_request_sent_to_the_model_is_forced_tool_only_and_carries_no_company_data(): void
    {
        $this->enableAi();
        ThirdPartner::factory()->customer()->create(['tp_title' => 'Quincaillerie Secrete']);
        $product = Product::factory()->create(['p_title' => 'Perceuse Confidentielle']);
        WarehouseHasStock::factory()->create(['warehouse_id' => Warehouse::first()->id, 'product_id' => $product->id, 'stockLevel' => 4242]);
        $this->aiReplies(['intent' => 'etat']);

        $this->say(self::FREE_STATUS);

        Http::assertSent(function (Request $request) {
            $body = $request->body();
            $this->assertSame('sk-test-cle', $request->header('x-api-key')[0]);
            $this->assertSame(OrchestratorInterpreter::DEFAULT_MODEL, $request['model']);
            $this->assertSame(['type' => 'tool', 'name' => 'route_request'], $request['tool_choice']);
            $this->assertStringContainsString('<message>', $request['messages'][0]['content']);
            // Seuls la phrase et les noms d'entrepôts sont transmis : ni client, ni article, ni quantité.
            foreach (['Quincaillerie Secrete', 'Perceuse Confidentielle', '4242'] as $secret) {
                $this->assertStringNotContainsString($secret, $body);
            }
            $this->assertStringContainsString('Magasin Centre', $body);

            return true;
        });
    }

    // ── Ordres : proposés, jamais lancés seuls ───────────────────────

    public function test_an_order_is_only_proposed_and_runs_after_the_admin_confirms(): void
    {
        $this->enableAi();
        $this->aiReplies(['intent' => 'inventaire_ordre', 'warehouse' => 'magasin centre', 'scope' => 'attention']);

        $proposal = $this->say(self::FREE_ORDER);

        $this->assertStringContainsString("Un ordre ne part que sur une demande explicite", $proposal->json('reply.body'));
        $this->assertSame('prépare un inventaire du Magasin Centre des articles à vérifier', $proposal->json('reply.suggestions.0.text'));
        $this->assertSame(0, AgentEvent::count());                       // rien n'est parti

        // L'administrateur clique : la phrase canonique est traitée par les règles, sans modèle.
        Http::fake();
        $done = $this->say($proposal->json('reply.suggestions.0.text'));

        $this->assertStringContainsString("« Magasin Centre »", $done->json('reply.body'));
        $this->assertSame('inventaire_demande', AgentEvent::sole()->type);
        Http::assertNothingSent();
    }

    public function test_a_collections_order_is_proposed_not_executed(): void
    {
        $this->enableAi();
        $this->aiReplies(['intent' => 'encaissements_ordre']);

        $r = $this->say("fais le tour des factures qui n'ont pas été payées à temps");

        $this->assertSame('contrôle les encaissements', $r->json('reply.suggestions.0.text'));
        $this->assertSame(0, AgentEvent::count());
    }

    public function test_a_warehouse_invented_by_the_model_is_dropped(): void
    {
        $this->enableAi();
        $this->aiReplies(['intent' => 'inventaire_ordre', 'warehouse' => 'Entrepôt Fantôme', 'scope' => 'all']);

        $r = $this->say(self::FREE_ORDER);

        $this->assertSame('prépare un inventaire', $r->json('reply.suggestions.0.text'));
    }

    // ── Robustesse ───────────────────────────────────────────────────

    public function test_out_of_scope_invalid_or_failed_answers_fall_back_to_the_help(): void
    {
        $this->enableAi();

        $this->aiReplies(['intent' => 'hors_sujet']);
        $this->assertStringContainsString("Je n'ai pas compris", $this->say('quelle est la capitale du Maroc ?')->json('reply.body'));

        $this->aiReplies(['intent' => 'supprimer_toutes_les_donnees']);
        $this->assertStringContainsString("Je n'ai pas compris", $this->say(self::FREE_STATUS)->json('reply.body'));

        Http::fake(['api.anthropic.com/*' => Http::response('erreur', 500)]);
        $this->assertStringContainsString("Je n'ai pas compris", $this->say(self::FREE_STATUS)->json('reply.body'));

        Http::fake(fn () => throw new ConnectionException('délai dépassé'));
        $this->assertStringContainsString("Je n'ai pas compris", $this->say(self::FREE_STATUS)->json('reply.body'));

        $this->assertSame(0, AgentEvent::count());
    }

    public function test_the_daily_cap_stops_the_calls(): void
    {
        $this->enableAi();
        $this->aiReplies(['intent' => 'etat']);
        $interpreter = app(OrchestratorInterpreter::class);

        $answers = [];
        for ($i = 0; $i < OrchestratorInterpreter::DAILY_CAP + 3; $i++) {
            $answers[] = $interpreter->interpret(self::FREE_STATUS, ['Dépôt Principal']);
        }

        $this->assertNotNull($answers[OrchestratorInterpreter::DAILY_CAP - 1]);
        $this->assertNull($answers[OrchestratorInterpreter::DAILY_CAP]);
        Http::assertSentCount(OrchestratorInterpreter::DAILY_CAP);
    }

    // ── Interrupteur ─────────────────────────────────────────────────

    public function test_the_admin_can_switch_it_on_only_with_a_key_and_see_its_state(): void
    {
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/agents/orchestrateur/ia', ['enabled' => true])
            ->assertStatus(422);

        $state = $this->actingAs($this->admin, 'sanctum')->getJson('/api/agents/orchestrateur')->assertOk()->json('ai');
        $this->assertSame(['configured' => false, 'enabled' => false, 'model' => OrchestratorInterpreter::DEFAULT_MODEL], $state);

        Setting::set('messaging', 'anthropic_api_key', encrypt('sk-test-cle'));
        $on = $this->actingAs($this->admin, 'sanctum')->putJson('/api/agents/orchestrateur/ia', ['enabled' => true])->assertOk();
        $this->assertTrue($on->json('enabled'));

        $off = $this->actingAs($this->admin, 'sanctum')->putJson('/api/agents/orchestrateur/ia', ['enabled' => false])->assertOk();
        $this->assertFalse($off->json('enabled'));
        $this->assertTrue($off->json('configured'));

        $this->actingAs($this->admin, 'sanctum')->putJson('/api/agents/orchestrateur/ia', ['enabled' => 'peut-etre'])->assertStatus(422);
    }

    public function test_the_switch_is_for_administrators_only(): void
    {
        foreach (['manager', 'cashier', 'warehouse'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create(), 'sanctum')
                ->putJson('/api/agents/orchestrateur/ia', ['enabled' => false])->assertForbidden();
        }
    }

    public function test_suggestions_and_the_ai_flag_come_back_with_the_history(): void
    {
        $this->enableAi();
        $this->aiReplies(['intent' => 'inventaire_ordre']);
        $this->say(self::FREE_ORDER);

        $messages = $this->actingAs($this->admin, 'sanctum')->getJson('/api/agents/orchestrateur')->json('messages');

        $reply = collect($messages)->firstWhere('role', 'orchestrator');
        $this->assertTrue($reply['ai']);
        $this->assertSame('prépare un inventaire', $reply['suggestions'][0]['text']);
    }
}
