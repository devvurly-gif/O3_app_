<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\AgentDirective;
use App\Models\AgentEvent;
use App\Models\AgentRoutine;
use App\Models\Category;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\OrchestratorMessage;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use App\Services\Agents\AgentDataTools;
use App\Services\Agents\RoutineRunner;
use App\Services\Agents\RoutineSchedule;
use App\Services\Agents\RoutineSteps;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * L'atelier des agents : recruter un agent de lecture, planifier des routines, retenir des consignes, catalogue
 * des tâches et demandes de développement. Aucun appel réel à l'API : les réponses du modèle sont simulées.
 */
class OrchestratorStudioTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function say(string $text): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated();
    }

    private function enableAi(): void
    {
        Setting::set('messaging', 'anthropic_api_key', encrypt('sk-test-cle'));
        Setting::set('agents', 'orchestrator_ai_enabled', 'true');
    }

    private function toolUse(string $name, array $input, string $id = 'toolu_1'): array
    {
        return ['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input];
    }

    /** Une suite de réponses du modèle (une par appel). @param array<int, array<int, array<string, mixed>>> $responses blocs de contenu */
    private function modelSays(array ...$responses): void
    {
        // Http::fake garde le premier stub qui correspond : on repart d'un client neuf à chaque série de réponses.
        Http::swap(new Factory());
        $this->enableAi();
        $sequence = Http::sequence();
        foreach ($responses as $content) {
            $sequence->pushResponse(Http::response(['content' => $content, 'stop_reason' => 'tool_use']));
        }
        Http::fake(['api.anthropic.com/*' => $sequence]);
    }

    private function stockAgent(bool $active = true, array $scopes = ['stock']): Agent
    {
        return Agent::create([
            'domain' => 'perso-veille-stock', 'name' => 'Veille stock', 'kind' => 'custom', 'mission' => 'Signale les produits en rupture de stock.',
            'scopes' => $scopes, 'created_by' => $this->admin->id, 'is_active' => $active, 'default_level' => 'approval',
        ]);
    }

    // ── Horaires ─────────────────────────────────────────────────────

    public function test_schedules_are_validated_and_the_next_run_is_computed_in_the_tenant_timezone(): void
    {
        $this->assertNull(RoutineSchedule::sanitize(['frequency' => 'hourly', 'time' => '08:00']));
        $this->assertNull(RoutineSchedule::sanitize(['frequency' => 'daily', 'time' => '25:00']));
        $this->assertNull(RoutineSchedule::sanitize(['frequency' => 'weekly', 'time' => '08:00']));              // jour manquant
        $this->assertNull(RoutineSchedule::sanitize(['frequency' => 'monthly', 'day' => 31, 'time' => '08:00'])); // 28 au plus
        $this->assertSame(['frequency' => 'weekly', 'time' => '08:00', 'weekday' => 1], RoutineSchedule::sanitize(['frequency' => 'weekly', 'weekday' => 1, 'time' => '8:00']));

        Setting::set('locale', 'timezone', 'Etc/GMT-1');   // UTC+1 fixe : indépendant des règles d'heure d'été de la base de fuseaux
        Carbon::setTestNow(Carbon::parse('2026-10-06 06:00:00', 'UTC'));   // mardi 06/10, 07:00 en UTC+1

        $monday = RoutineSchedule::next(['frequency' => 'weekly', 'weekday' => 1, 'time' => '08:00']);
        $this->assertSame('2026-10-12 07:00:00', $monday->format('Y-m-d H:i:s'));                       // lundi prochain 08:00 local = 07:00 UTC

        $daily = RoutineSchedule::next(['frequency' => 'daily', 'time' => '09:15']);
        $this->assertSame('2026-10-06 08:15:00', $daily->format('Y-m-d H:i:s'));                         // aujourd'hui, pas encore passée

        $past = RoutineSchedule::next(['frequency' => 'daily', 'time' => '06:30']);
        $this->assertSame('2026-10-07 05:30:00', $past->format('Y-m-d H:i:s'));                          // déjà passée : demain

        $monthly = RoutineSchedule::next(['frequency' => 'monthly', 'day' => 5, 'time' => '07:30']);
        $this->assertSame('2026-11-05 06:30:00', $monthly->format('Y-m-d H:i:s'));

        $this->assertSame('chaque lundi à 08:00', RoutineSchedule::describe(['frequency' => 'weekly', 'weekday' => 1, 'time' => '08:00']));
        $this->assertSame('tous les jours à 09:15', RoutineSchedule::describe(['frequency' => 'daily', 'time' => '09:15']));
    }

    // ── Recruter un agent ────────────────────────────────────────────

    public function test_recruiting_shows_the_card_with_what_it_can_read_and_creates_nothing_until_the_click(): void
    {
        $this->modelSays([$this->toolUse('design_agent', [
            'feasible' => true, 'name' => 'Veille stock', 'mission' => 'Signale chaque matin les produits dont le stock est inférieur à 5.',
            'scopes' => ['stock', 'ventes', 'inexistant'], 'schedule' => ['frequency' => 'daily', 'time' => '08:00'],
        ])]);

        $r = $this->say('recrute un agent qui surveille les ruptures de stock chaque matin');

        $event = AgentEvent::where('type', 'agent_recrutement')->firstOrFail();
        $body = $r->json('reply.body');
        $this->assertStringContainsString('Agent proposé : « Veille stock »', $body);
        $this->assertStringContainsString('• Stock : quantités en stock', $body);
        $this->assertStringContainsString('• Ventes :', $body);
        $this->assertStringNotContainsString('inexistant', $body);                    // domaine inventé : écarté
        $this->assertStringContainsString('envoyées à Anthropic', $body);              // ventes = données sensibles
        $this->assertStringContainsString('tous les jours à 08:00', $body);
        $this->assertStringContainsString("Il ne peut rien écrire", $body);
        $this->assertSame("applique la proposition #{$event->id}", $r->json('reply.suggestions.0.text'));
        $this->assertSame(0, Agent::where('kind', 'custom')->count());                // rien avant le clic
    }

    public function test_the_click_creates_an_inactive_agent_with_its_paused_routine_and_it_cannot_be_applied_twice(): void
    {
        $this->modelSays([$this->toolUse('design_agent', [
            'feasible' => true, 'name' => 'Veille stock', 'mission' => 'Signale les produits en rupture de stock.',
            'scopes' => ['stock'], 'schedule' => ['frequency' => 'daily', 'time' => '08:00'],
        ])]);
        $this->say('recrute un agent qui surveille le stock chaque matin');
        $event = AgentEvent::where('type', 'agent_recrutement')->firstOrFail();

        $done = $this->say("applique la proposition #{$event->id}");

        $agent = Agent::where('kind', 'custom')->firstOrFail();
        $this->assertFalse($agent->is_active);                                         // naît inactif
        $this->assertSame(['stock'], $agent->scopes);
        $this->assertTrue($agent->isCustom());
        $this->assertSame($this->admin->id, $agent->created_by);
        $routine = AgentRoutine::firstOrFail();
        $this->assertFalse($routine->is_active);                                       // démarre à l'activation de l'agent
        $this->assertSame(["agent:{$agent->id}"], $routine->steps);
        $this->assertStringContainsString("Agent #{$agent->id} « Veille stock » recruté, inactif", $done->json('reply.body'));
        $this->assertSame("active l'agent #{$agent->id}", $done->json('reply.suggestions.0.text'));
        $this->assertSame('done', $event->fresh()->status);
        $this->assertSame('agent_recruited', \App\Models\AgentAction::where('agent_id', $agent->id)->firstOrFail()->action);

        $this->assertStringContainsString('déjà été traitée', $this->say("applique la proposition #{$event->id}")->json('reply.body'));
        $this->assertSame(1, Agent::where('kind', 'custom')->count());
    }

    public function test_a_mission_the_system_cannot_fulfil_is_refused_with_a_way_to_request_the_development(): void
    {
        $this->modelSays([$this->toolUse('design_agent', ['feasible' => false, 'missing' => "il faudrait envoyer des messages WhatsApp aux clients"])]);

        $r = $this->say('crée un agent qui envoie un message WhatsApp aux clients en retard');

        $this->assertStringContainsString('Je ne peux pas recruter cet agent tel quel', $r->json('reply.body'));
        $this->assertStringContainsString('envoyer des messages WhatsApp', $r->json('reply.body'));
        $this->assertStringContainsString('demande de développement', $r->json('reply.suggestions.0.text'));
        $this->assertSame(0, AgentEvent::where('type', 'agent_recrutement')->count());
        $this->assertSame(0, Agent::where('kind', 'custom')->count());
    }

    public function test_a_card_without_valid_data_domains_is_never_proposed(): void
    {
        $this->modelSays([$this->toolUse('design_agent', ['feasible' => true, 'name' => 'Agent fantôme', 'mission' => 'Fait des choses.', 'scopes' => ['base_de_donnees_complete']])]);

        $r = $this->say('recrute un agent fantôme');

        $this->assertStringContainsString('Je ne peux pas recruter cet agent tel quel', $r->json('reply.body'));
        $this->assertSame(0, AgentEvent::where('type', 'agent_recrutement')->count());
    }

    public function test_recruiting_and_routines_need_the_ai(): void
    {
        Http::fake();

        $this->assertStringContainsString('compréhension avancée', $this->say('recrute un agent qui surveille le stock')->json('reply.body'));
        $this->assertStringContainsString('compréhension avancée', $this->say('chaque lundi à 8 h contrôle les encaissements')->json('reply.body'));
        Http::assertNothingSent();
        $this->assertSame(0, AgentEvent::where('type', 'controle_encaissements')->count());   // une routine n'est jamais exécutée en la décrivant
    }

    // ── Activer, lancer ──────────────────────────────────────────────

    public function test_activating_an_agent_resumes_its_routine_and_deactivating_pauses_it(): void
    {
        $agent = $this->stockAgent(false);
        $routine = AgentRoutine::create(['name' => 'Routine veille', 'steps' => ["agent:{$agent->id}"], 'schedule' => ['frequency' => 'daily', 'time' => '08:00'], 'agent_id' => $agent->id, 'is_active' => false, 'created_by' => $this->admin->id]);

        $on = $this->say("active l'agent #{$agent->id}");
        $this->assertTrue($agent->fresh()->is_active);
        $this->assertTrue($routine->fresh()->is_active);
        $this->assertNotNull($routine->fresh()->next_run_at);
        $this->assertStringContainsString('activé', $on->json('reply.body'));

        $this->say("désactive l'agent #{$agent->id}");
        $this->assertFalse($agent->fresh()->is_active);
        $this->assertFalse($routine->fresh()->is_active);
        $this->assertNull($routine->fresh()->next_run_at);

        // Les agents du socle ne s'activent pas depuis ici : ils se règlent dans l'écran Activité des agents.
        $builtin = Agent::where('domain', 'recouvrement')->firstOrFail();
        $this->assertStringContainsString('Je ne trouve pas', $this->say("active l'agent #{$builtin->id}")->json('reply.body'));
        $this->assertFalse($builtin->fresh()->is_active);
    }

    public function test_a_recruited_agent_reads_with_its_tools_then_reports_and_only_proposes_known_actions(): void
    {
        $agent = $this->stockAgent();
        $warehouse = Warehouse::factory()->create(['wh_status' => true, 'wh_title' => 'Dépôt Principal']);
        $low = Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18']);
        $ok = Product::factory()->create(['p_title' => 'Marteau', 'p_sku' => 'MRT01']);
        WarehouseHasStock::factory()->create(['warehouse_id' => $warehouse->id, 'product_id' => $low->id, 'stockLevel' => 2]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $warehouse->id, 'product_id' => $ok->id, 'stockLevel' => 40]);

        $this->modelSays(
            [$this->toolUse('stock_bas', ['seuil' => 5], 'toolu_a')],
            [$this->toolUse('submit_report', ['report' => 'Un produit est sous le seuil : Perceuse 18V (2).', 'level' => 'attention', 'proposals' => ['inventaire', 'ordre_inventé', 'inventaire']], 'toolu_b')],
        );

        $r = $this->say("lance l'agent #{$agent->id}");

        $this->assertStringContainsString("Agent « Veille stock » — rapport (à surveiller)", $r->json('reply.body'));
        $this->assertStringContainsString('Perceuse 18V (2)', $r->json('reply.body'));
        $this->assertStringContainsString("l'agent a seulement lu les données", $r->json('reply.body'));
        // Une seule proposition, parmi les actions connues : l'ordre inventé et le doublon sont écartés.
        $this->assertSame(['prépare un inventaire'], array_column($r->json('reply.suggestions'), 'text'));
        $report = AgentEvent::where('type', 'agent_perso_rapport')->firstOrFail();
        $this->assertSame(['stock_bas' => 1], $report->payload['tools']);

        // Le résultat de l'outil lu dans la base (référence du produit en rupture) est bien revenu au modèle.
        Http::assertSent(function (Request $req) {
            $messages = $req->data()['messages'] ?? [];
            $last = end($messages);

            return is_array($last['content'] ?? null) && ($last['content'][0]['type'] ?? null) === 'tool_result'
                && str_contains($last['content'][0]['content'], 'PRC18') && !str_contains($last['content'][0]['content'], 'MRT01');
        });
        // Les outils offerts sont ceux des domaines de l'agent, plus le rapport : pas les créances.
        Http::assertSent(function (Request $req) {
            $names = array_column($req->data()['tools'] ?? [], 'name');

            return in_array('stock_bas', $names, true) && in_array('submit_report', $names, true) && !in_array('creances', $names, true);
        });
        // Aucune écriture : les produits et leur stock sont intacts.
        $this->assertSame(2.0, (float) WarehouseHasStock::where('product_id', $low->id)->value('stockLevel'));
    }

    public function test_an_inactive_agent_is_not_run_and_a_failing_run_says_why(): void
    {
        $agent = $this->stockAgent(false);
        Http::fake();

        $this->assertStringContainsString('est inactif', $this->say("lance l'agent #{$agent->id}")->json('reply.body'));
        Http::assertNothingSent();

        $agent->update(['is_active' => true]);
        $this->enableAi();
        Http::swap(new Factory());
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Your credit balance is too low']], 400)]);
        $this->assertStringContainsString('crédit', $this->say("lance l'agent #{$agent->id}")->json('reply.body'));
        $this->assertSame(0, AgentEvent::where('type', 'agent_perso_rapport')->count());
    }

    public function test_the_loop_is_bounded_when_the_agent_never_reports(): void
    {
        $agent = $this->stockAgent();
        $this->modelSays(...array_fill(0, 6, [$this->toolUse('stock_bas', [], 'toolu_x')]));

        $r = $this->say("lance l'agent #{$agent->id}");

        $this->assertStringContainsString("n'a pas terminé son analyse en 6 échanges", $r->json('reply.body'));
    }

    // ── Outils de lecture ────────────────────────────────────────────

    public function test_an_agent_can_only_use_the_tools_of_its_granted_domains_and_never_writes(): void
    {
        $tools = app(AgentDataTools::class);

        $this->assertStringContainsString('non autorisé', $tools->run('creances', [], ['stock'])['erreur']);
        $this->assertStringContainsString('non autorisé', $tools->run('DROP TABLE products', [], ['stock'])['erreur']);
        $this->assertSame(['stock', 'ventes'], AgentDataTools::sanitizeScopes(['stock', 'ventes', 'x', 'stock', 5]));
        $names = array_column($tools->definitions(['stock']), 'name');
        $this->assertSame(['stock_bas'], $names);
        $this->assertSame([], $tools->definitions(['inexistant']));

        // Chaque outil annoncé par un domaine a une définition ET un gestionnaire (aucun outil « fantôme »).
        foreach (AgentDataTools::SCOPES as $scope => $info) {
            foreach ($info['tools'] as $tool) {
                $this->assertContains($tool, array_column($tools->definitions([$scope]), 'name'));
                $result = $tools->run($tool, ['recherche' => 'ab', 'seuil' => 5], [$scope]);
                $this->assertIsArray($result);
                $this->assertStringNotContainsString('Outil inconnu', json_encode($result));
            }
        }
    }

    public function test_the_read_tools_return_bounded_real_data(): void
    {
        $tools = app(AgentDataTools::class);
        $warehouse = Warehouse::factory()->create(['wh_status' => true, 'wh_title' => 'Dépôt Principal']);
        $other = Warehouse::factory()->create(['wh_status' => true, 'wh_title' => 'Magasin Centre']);
        $a = Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18', 'p_salePrice' => 100]);
        $b = Product::factory()->create(['p_title' => 'Marteau', 'p_sku' => 'MRT01']);
        $c = Product::factory()->create(['p_title' => 'Sans stock', 'p_sku' => 'NOSTK']);
        WarehouseHasStock::factory()->create(['warehouse_id' => $warehouse->id, 'product_id' => $a->id, 'stockLevel' => 3]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $other->id, 'product_id' => $b->id, 'stockLevel' => 3]);

        $all = $tools->run('stock_bas', ['seuil' => 5], ['stock']);
        $this->assertSame(3, $all['produits_concernes']);                               // a, b et c (sans ligne de stock)
        $this->assertSame(1, $all['en_rupture']);                                       // c
        $this->assertSame('NOSTK', $all['plus_bas'][0]['reference']);                   // les plus bas d'abord

        $one = $tools->run('stock_bas', ['seuil' => 5, 'entrepot' => 'dépôt principal'], ['stock']);
        $this->assertContains('PRC18', array_column($one['plus_bas'], 'reference'));
        $this->assertStringContainsString('introuvable', $tools->run('stock_bas', ['entrepot' => 'Inconnu'], ['stock'])['erreur']);

        $search = $tools->run('produit_recherche', ['recherche' => 'perceuse'], ['produits']);
        $this->assertSame('PRC18', $search['resultats'][0]['reference']);
        $this->assertSame(3.0, $search['resultats'][0]['stock']);
        $this->assertStringContainsString('trop courte', $tools->run('produit_recherche', ['recherche' => 'p'], ['produits'])['erreur']);

        $quality = $tools->run('qualite_fiches', [], ['catalogue']);
        $this->assertSame(3, $quality['fiches']);
        $this->assertArrayHasKey('no_photo', $quality['manques']);
    }

    public function test_the_receivables_tool_lists_overdue_invoices_with_amounts(): void
    {
        $customer = ThirdPartner::factory()->customer()->create(['tp_title' => 'Quincaillerie Atlas']);
        $overdue = DocumentHeader::factory()->create(['thirdPartner_id' => $customer->id, 'reference' => 'FV-001', 'issued_at' => now()->subDays(40), 'due_at' => now()->subDays(10)]);
        DocumentFooter::factory()->create(['document_header_id' => $overdue->id, 'total_ttc' => 1200, 'amount_paid' => 0, 'amount_due' => 1200]);
        $notYet = DocumentHeader::factory()->create(['thirdPartner_id' => $customer->id, 'reference' => 'FV-002', 'issued_at' => now(), 'due_at' => now()->addDays(20)]);
        DocumentFooter::factory()->create(['document_header_id' => $notYet->id, 'total_ttc' => 500, 'amount_paid' => 0, 'amount_due' => 500]);

        $r = app(AgentDataTools::class)->run('creances', [], ['creances']);

        $this->assertSame(1, $r['factures_en_retard']);
        $this->assertSame(1200.0, $r['total_du']);
        $this->assertSame('FV-001', $r['plus_importantes'][0]['reference']);
        $this->assertSame('Quincaillerie Atlas', $r['plus_importantes'][0]['client']);

        $sales = app(AgentDataTools::class)->run('ventes_resume', ['jours' => 90], ['ventes']);
        $this->assertSame(2, $sales['factures']);
        $this->assertSame(1700.0, $sales['total_ttc']);
    }

    // ── Routines ─────────────────────────────────────────────────────

    public function test_a_routine_is_proposed_then_planned_at_the_click_and_can_be_paused_resumed_and_deleted(): void
    {
        $this->modelSays([$this->toolUse('design_routine', [
            'feasible' => true, 'name' => 'Contrôle hebdomadaire', 'steps' => ['encaissements', 'fiches', 'etape_inventee'],
            'schedule' => ['frequency' => 'weekly', 'weekday' => 1, 'time' => '08:00'],
        ])]);

        $r = $this->say('chaque lundi à 8 h, contrôle les encaissements et les fiches produits');

        $event = AgentEvent::where('type', 'routine_proposition')->firstOrFail();
        $this->assertStringContainsString('chaque lundi à 08:00', $r->json('reply.body'));
        $this->assertStringContainsString('1. Contrôler les encaissements', $r->json('reply.body'));
        $this->assertStringContainsString('2. Contrôler les fiches produits', $r->json('reply.body'));
        $this->assertStringNotContainsString('etape_inventee', $r->json('reply.body'));
        $this->assertStringContainsString("rien n'est appliqué ni envoyé", $r->json('reply.body'));
        $this->assertSame(0, AgentRoutine::count());                                   // proposition seulement
        $this->assertSame(0, AgentEvent::where('type', 'controle_encaissements')->count());

        $this->say("applique la proposition #{$event->id}");
        $routine = AgentRoutine::firstOrFail();
        $this->assertSame(['encaissements', 'fiches'], $routine->steps);
        $this->assertTrue($routine->is_active);
        $this->assertNotNull($routine->next_run_at);

        $this->assertStringContainsString("#{$routine->id} « Contrôle hebdomadaire »", $this->say('mes routines')->json('reply.body'));

        $this->say("mets en pause la routine #{$routine->id}");
        $this->assertFalse($routine->fresh()->is_active);
        $this->assertNull($routine->fresh()->next_run_at);

        $this->say("reprends la routine #{$routine->id}");
        $this->assertTrue($routine->fresh()->is_active);
        $this->assertNotNull($routine->fresh()->next_run_at);

        $this->say("supprime la routine #{$routine->id}");
        $this->assertSame(0, AgentRoutine::count());
    }

    public function test_only_known_safe_steps_can_be_scheduled_and_nothing_can_be_applied_by_a_routine(): void
    {
        $custom = $this->stockAgent();

        $this->assertSame(['etat', 'fiches', "agent:{$custom->id}"], RoutineSteps::sanitize(['etat', 'applique les propositions du lot #1', 'fiches', 'DROP', 'agent:99999', "agent:{$custom->id}", 'etat']));
        foreach (RoutineSteps::KNOWN as $step) {
            $this->assertDoesNotMatchRegularExpression('/appliqu|activ|attribue|supprim/', $step['phrase'], "L'étape « {$step['label']} » ne doit pas écrire.");
        }
    }

    public function test_running_a_routine_chains_its_steps_posts_the_report_and_reports_a_partial_failure(): void
    {
        $routine = AgentRoutine::create([
            'name' => 'Point du matin', 'steps' => ['etat', 'encaissements', 'fiches'], 'schedule' => ['frequency' => 'daily', 'time' => '08:00'],
            'is_active' => true, 'created_by' => $this->admin->id, 'next_run_at' => now()->subMinute(),
        ]);

        // L'agent Recouvrement est inactif (socle) : l'étape « encaissements » ne se fait pas, les autres oui.
        $r = $this->say("lance la routine #{$routine->id}");

        $body = $r->json('reply.body');
        $this->assertStringContainsString('Routine « Point du matin » (lancée à la demande)', $body);
        $this->assertStringContainsString('— État des agents —', $body);
        $this->assertStringContainsString('— Contrôler les encaissements (non réalisée) —', $body);
        $this->assertStringContainsString('— Contrôler les fiches produits —', $body);
        $this->assertSame('partial', $routine->fresh()->last_status);
        $this->assertNotNull($routine->fresh()->last_run_at);
        $this->assertSame('routine_executee', AgentEvent::where('type', 'routine_executee')->firstOrFail()->type);
    }

    public function test_due_routines_run_once_per_due_date_and_the_report_lands_in_the_creators_chat(): void
    {
        $due = AgentRoutine::create(['name' => 'Échue', 'steps' => ['etat'], 'schedule' => ['frequency' => 'daily', 'time' => '08:00'], 'is_active' => true, 'created_by' => $this->admin->id, 'next_run_at' => now()->subMinutes(5)]);
        $future = AgentRoutine::create(['name' => 'Future', 'steps' => ['etat'], 'schedule' => ['frequency' => 'daily', 'time' => '08:00'], 'is_active' => true, 'created_by' => $this->admin->id, 'next_run_at' => now()->addHour()]);
        $paused = AgentRoutine::create(['name' => 'En pause', 'steps' => ['etat'], 'schedule' => ['frequency' => 'daily', 'time' => '08:00'], 'is_active' => false, 'created_by' => $this->admin->id, 'next_run_at' => now()->subMinutes(5)]);
        $runner = app(RoutineRunner::class);

        $this->assertSame(1, $runner->runDue());
        $this->assertSame(0, $runner->runDue());                                       // l'échéance a été réservée : pas de seconde exécution

        $this->assertNotNull($due->fresh()->last_run_at);
        $this->assertTrue($due->fresh()->next_run_at->isFuture());
        $this->assertNull($future->fresh()->last_run_at);
        $this->assertNull($paused->fresh()->last_run_at);
        $message = OrchestratorMessage::where('user_id', $this->admin->id)->where('role', 'orchestrator')->firstOrFail();
        $this->assertStringContainsString('Routine « Échue » (planifiée)', $message->body);
    }

    public function test_a_routine_of_a_deleted_or_inactive_admin_stops_and_says_so(): void
    {
        $other = User::factory()->admin()->create(['is_active' => false]);
        $routine = AgentRoutine::create(['name' => 'Orpheline', 'steps' => ['etat'], 'schedule' => ['frequency' => 'daily', 'time' => '08:00'], 'is_active' => true, 'created_by' => $other->id, 'next_run_at' => now()->subMinute()]);

        app(RoutineRunner::class)->runDue();

        $this->assertSame('error', $routine->fresh()->last_status);
        $this->assertStringContainsString('arrêtée', $routine->fresh()->last_summary);
    }

    public function test_a_routine_step_that_runs_a_recruited_agent_reports_its_findings(): void
    {
        $agent = $this->stockAgent();
        $routine = AgentRoutine::create(['name' => 'Veille', 'steps' => ["agent:{$agent->id}"], 'schedule' => ['frequency' => 'daily', 'time' => '08:00'], 'agent_id' => $agent->id, 'is_active' => true, 'created_by' => $this->admin->id, 'next_run_at' => now()->subMinute()]);
        $this->modelSays([$this->toolUse('submit_report', ['report' => 'Aucun produit sous le seuil.', 'level' => 'ok', 'proposals' => []])]);

        $out = app(RoutineRunner::class)->run($routine);

        $this->assertSame('ok', $out['status']);
        $this->assertStringContainsString('— Agent Veille stock —', $out['body']);
        $this->assertStringContainsString('Aucun produit sous le seuil.', $out['body']);
    }

    // ── Consignes ────────────────────────────────────────────────────

    public function test_a_directive_is_proposed_retained_at_the_click_listed_removed_and_read_by_new_agents(): void
    {
        $r = $this->say('retiens : marge minimale de 20 % sur tous les produits');

        $event = AgentEvent::where('type', 'consigne_proposition')->firstOrFail();
        $this->assertStringContainsString('« marge minimale de 20 % sur tous les produits »', $r->json('reply.body'));
        $this->assertStringContainsString('ne les change pas', $r->json('reply.body'));   // les agents du socle ont leurs propres règles
        $this->assertSame(0, AgentDirective::count());

        $this->say("applique la proposition #{$event->id}");
        $directive = AgentDirective::firstOrFail();
        $this->assertSame('marge minimale de 20 % sur tous les produits', $directive->body);
        $this->assertStringContainsString("#{$directive->id} marge minimale", $this->say('quelles sont les consignes ?')->json('reply.body'));

        // Lue par l'atelier (recrutement) et par l'agent recruté.
        $this->modelSays([$this->toolUse('design_agent', ['feasible' => false, 'missing' => 'test'])]);
        $this->say('recrute un agent qui surveille les prix');
        Http::assertSent(fn (Request $req) => str_contains($req->data()['system'] ?? '', 'marge minimale de 20 %'));

        $agent = $this->stockAgent();
        $this->modelSays([$this->toolUse('submit_report', ['report' => 'Rien à signaler pour le moment.', 'level' => 'ok'])]);
        $this->say("lance l'agent #{$agent->id}");
        Http::assertSent(fn (Request $req) => str_contains($req->data()['system'] ?? '', 'Règles de la maison') && str_contains($req->data()['system'] ?? '', 'marge minimale de 20 %'));

        $this->say("oublie la consigne #{$directive->id}");
        $this->assertFalse($directive->fresh()->is_active);
        $this->assertStringContainsString('Aucune consigne', $this->say('mes consignes')->json('reply.body'));
    }

    // ── Catalogue des tâches et demandes de développement ────────────

    public function test_the_catalogue_lists_what_each_agent_can_do_and_what_is_missing(): void
    {
        $r = $this->say('que sait faire chaque agent ?');

        $body = $r->json('reply.body');
        $this->assertStringContainsString('• Achats & catalogue (actif)', $body);
        $this->assertStringContainsString('« prépare les fiches pour l\'utilisation »', $body);
        $this->assertStringContainsString('• Recouvrement (inactif)', $body);
        $this->assertStringContainsString("• Expédition (actif)\n   Aucune tâche pour l'instant.", $body);
        $this->assertStringContainsString('Pas encore : préparer les livraisons du jour', $body);
        $this->assertContains("demande de développement pour l'agent Expédition", array_column($r->json('reply.suggestions'), 'text'));
    }

    public function test_a_development_request_is_written_recorded_listed_and_closed(): void
    {
        $r = $this->say("demande de développement pour l'agent Expédition : préparer les livraisons du jour");

        $event = AgentEvent::where('type', 'demande_developpement')->firstOrFail();
        $brief = $event->payload['brief'];
        $this->assertStringContainsString('DEMANDE DE DÉVELOPPEMENT', $brief);
        $this->assertStringContainsString('préparer les livraisons du jour', $brief);
        $this->assertStringContainsString('Idées déjà identifiées pour cet agent', $brief);
        $this->assertStringContainsString('rien ne s\'écrit sans validation', $brief);
        $this->assertStringContainsString($brief, $r->json('reply.body'));
        $this->assertSame('new', $event->status);

        $this->assertStringContainsString("#{$event->id}", $this->say('mes demandes de développement')->json('reply.body'));

        $this->say("la demande #{$event->id} est faite");
        $this->assertSame('done', $event->fresh()->status);
        $this->assertStringContainsString('Aucune demande', $this->say('mes demandes de développement')->json('reply.body'));

        $this->assertStringContainsString('Décrivez la tâche', $this->say('demande de développement')->json('reply.body'));
    }

    // ── L'atelier ne détourne pas les demandes existantes ────────────

    public function test_existing_requests_are_not_hijacked_by_the_studio(): void
    {
        Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18']);
        $this->assertSame(1, Category::count());                                      // celle du produit de test

        $this->assertStringContainsString('Contrôle des fiches produits', $this->say('mettre à jour les fiches produits')->json('reply.body'));
        $this->assertStringContainsString('Situation du', $this->say('état des agents')->json('reply.body'));
        $this->assertStringContainsString('Cela se passe dans', $this->say('où créer une facture ?')->json('reply.body'));
        $this->say('quels produits sont sans photo ?');                                // « tous les produits » n'est pas un horaire
        $this->say('liste tous les produits sans photo');

        // Ordre explicite : la demande immédiate part toujours vers l'agent concerné (refusée ici, Recouvrement inactif).
        $this->say('contrôle les encaissements');
        $this->assertSame(1, AgentEvent::where('type', 'controle_encaissements')->count());
        $this->assertSame(0, AgentRoutine::count());
        $this->assertSame(0, Agent::where('kind', 'custom')->count());
    }
}
