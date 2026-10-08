<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\AgentEvent;
use App\Models\AgentRoutine;
use App\Models\DocumentHeader;
use App\Models\DocumentFooter;
use App\Models\OrchestratorMessage;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Agents\AgentTriggers;
use App\Services\Agents\RoutineRunner;
use App\Services\StockMouvementService;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Les routines déclenchées par un événement interne d'O3 : émission sans coût tant que personne n'écoute,
 * conditions, déduplication, un seul passage par événement, délai minimal et contexte donné à l'agent.
 */
class AgentTriggersTest extends TestCase
{
    use RefreshTenantDatabase, InteractsWithTenancy;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->fakeTenant(['plan' => 'pro', 'status' => \App\Enums\TenantStatus::Active, 'agents_enabled' => true]);          // un client Pro payé, option « Agents IA » allumée
    }

    private function eventRoutine(array $trigger, array $steps = ['etat'], array $over = []): AgentRoutine
    {
        $routine = AgentRoutine::create(array_merge([
            'name' => 'À l\'événement', 'steps' => $steps, 'schedule' => ['frequency' => 'event'],
            'trigger' => AgentTriggers::sanitize($trigger), 'last_event_id' => (int) AgentEvent::max('id'),
            'is_active' => true, 'created_by' => $this->admin->id,
        ], $over));
        AgentTriggers::forgetListeners();

        return $routine;
    }

    // ── Déclencheurs : forme et conditions ───────────────────────────

    public function test_triggers_are_validated_and_conditions_are_matched(): void
    {
        $this->assertNull(AgentTriggers::sanitize(['event_type' => 'inconnu']));
        $this->assertNull(AgentTriggers::sanitize('texte'));

        $t = AgentTriggers::sanitize(['event_type' => 'stock_bas', 'conditions' => ['qty_lte' => '3', 'min_amount' => 50, 'autre' => 1], 'cooldown_minutes' => 5]);
        $this->assertSame(['event_type' => 'stock_bas', 'conditions' => ['qty_lte' => 3], 'cooldown_minutes' => 15], $t);   // condition étrangère écartée, délai relevé à 15 min
        $this->assertSame(1440, AgentTriggers::sanitize(['event_type' => 'produit_cree', 'cooldown_minutes' => 99999])['cooldown_minutes']);
        $this->assertSame(60, AgentTriggers::sanitize(['event_type' => 'produit_cree'])['cooldown_minutes']);
        $this->assertSame(['min_amount' => 500.0], AgentTriggers::sanitize(['event_type' => 'facture_vente_confirmee', 'conditions' => ['min_amount' => 500]])['conditions']);

        $low = new AgentEvent(['type' => 'stock_bas', 'payload' => ['qty' => 2]]);
        $this->assertTrue(AgentTriggers::matches($t, $low));
        $this->assertFalse(AgentTriggers::matches($t, new AgentEvent(['type' => 'stock_bas', 'payload' => ['qty' => 4]])));
        $this->assertFalse(AgentTriggers::matches($t, new AgentEvent(['type' => 'produit_cree', 'payload' => []])));

        $this->assertStringContainsString('stock de 3 pièce(s) ou moins', AgentTriggers::describe($t));
        $this->assertStringContainsString('au plus une exécution toutes les 15 min', AgentTriggers::describe($t));
        $this->assertStringContainsString('toutes les 2 h', AgentTriggers::describe(['event_type' => 'produit_cree', 'conditions' => [], 'cooldown_minutes' => 120]));
    }

    // ── Émission ─────────────────────────────────────────────────────

    public function test_nothing_is_emitted_while_no_active_routine_listens(): void
    {
        Product::factory()->create();
        $this->assertSame(0, AgentEvent::where('type', 'produit_cree')->count());

        $routine = $this->eventRoutine(['event_type' => 'produit_cree']);
        $routine->update(['is_active' => false]);
        AgentTriggers::forgetListeners();
        Product::factory()->create();
        $this->assertSame(0, AgentEvent::where('type', 'produit_cree')->count());          // en pause : personne n'écoute

        $routine->update(['is_active' => true]);
        AgentTriggers::forgetListeners();
        $product = Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18']);

        $event = AgentEvent::where('type', 'produit_cree')->firstOrFail();
        $this->assertSame('PRC18', $event->payload['sku']);
        $this->assertSame($product->id, $event->payload['product_id']);
        $this->assertSame('erp', $event->source);
        $this->assertSame('done', $event->status);
    }

    public function test_a_failing_emission_never_breaks_the_operation_that_produced_it(): void
    {
        $this->eventRoutine(['event_type' => 'produit_cree']);
        AgentEvent::creating(fn () => throw new \RuntimeException('panne simulée de la journalisation'));

        try {
            $product = Product::factory()->create();                                       // ne lève rien malgré la panne
        } finally {
            AgentEvent::flushEventListeners();
        }

        $this->assertNotNull($product->id);
        $this->assertSame(0, AgentEvent::where('type', 'produit_cree')->count());
    }

    public function test_the_observer_registered_on_several_models_never_breaks_their_normal_operations(): void
    {
        $this->eventRoutine(['event_type' => 'facture_vente_confirmee']);

        $product = Product::factory()->create();                                            // création d'un produit
        $product->update(['p_title' => 'Renommé']);                                         // modification d'un produit
        $document = DocumentHeader::factory()->create(['document_type' => 'DeliveryNote']); // création d'un document
        $document->update(['notes' => 'modifié']);                                          // modification d'un document

        $this->assertSame('Renommé', $product->fresh()->p_title);
        $this->assertSame(0, AgentEvent::where('type', 'facture_vente_confirmee')->count());
    }

    public function test_a_confirmed_sale_invoice_emits_once_when_it_becomes_confirmed(): void
    {
        $this->eventRoutine(['event_type' => 'facture_vente_confirmee']);
        $invoice = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'status' => 'draft', 'reference' => 'FV-100']);
        DocumentFooter::factory()->create(['document_header_id' => $invoice->id, 'total_ttc' => 1200, 'amount_due' => 1200]);

        $invoice->update(['notes' => 'brouillon modifié']);
        $this->assertSame(0, AgentEvent::where('type', 'facture_vente_confirmee')->count());

        $invoice->update(['status' => 'confirmed']);
        $invoice->update(['notes' => 'déjà confirmée, modifiée']);

        $event = AgentEvent::where('type', 'facture_vente_confirmee')->firstOrFail();
        $this->assertSame('FV-100', $event->payload['reference']);
        $this->assertEquals(1200.0, $event->payload["amount"]);
        $this->assertSame(1, AgentEvent::where('type', 'facture_vente_confirmee')->count());
    }

    public function test_the_low_stock_signal_is_emitted_below_the_alert_threshold_once_a_day_per_product(): void
    {
        Setting::set('stock', 'seuil_alerte_stock', '5');
        $this->eventRoutine(['event_type' => 'stock_bas']);
        $warehouse = Warehouse::factory()->create(['wh_title' => 'Dépôt Principal', 'wh_status' => true]);
        $product = Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18']);
        $check = new \ReflectionMethod(StockMouvementService::class, 'checkLowStockAlert');
        $service = app(StockMouvementService::class);

        $check->invoke($service, $product->id, $warehouse->id, 8.0);                        // au-dessus du seuil : rien
        $this->assertSame(0, AgentEvent::where('type', 'stock_bas')->count());

        $check->invoke($service, $product->id, $warehouse->id, 4.0);
        $check->invoke($service, $product->id, $warehouse->id, 3.0);                        // même produit, même jour : pas de doublon
        $this->assertSame(1, AgentEvent::where('type', 'stock_bas')->count());
        $this->assertSame(4.0, (float) AgentEvent::where('type', 'stock_bas')->firstOrFail()->payload['qty']);
        $this->assertSame('Dépôt Principal', AgentEvent::where('type', 'stock_bas')->firstOrFail()->payload['warehouse']);

        AgentEvent::where('type', 'stock_bas')->update(['created_at' => now()->subHours(25)]);
        $check->invoke($service, $product->id, $warehouse->id, 2.0);                        // le lendemain : de nouveau
        $this->assertSame(2, AgentEvent::where('type', 'stock_bas')->count());
    }

    // ── Exécution par le planificateur ───────────────────────────────

    public function test_an_event_routine_runs_once_for_its_events_with_a_report_listing_them(): void
    {
        $routine = $this->eventRoutine(['event_type' => 'produit_cree', 'cooldown_minutes' => 15], ['etat', 'fiches']);
        $runner = app(RoutineRunner::class);

        $this->assertSame(0, $runner->runDue());                                            // aucun événement : rien

        Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18']);
        Product::factory()->create(['p_title' => 'Marteau', 'p_sku' => 'MRT01']);

        $this->assertSame(1, $runner->runDue());                                            // deux événements : UN compte rendu
        $body = OrchestratorMessage::where('user_id', $this->admin->id)->where('role', 'orchestrator')->latest('id')->firstOrFail()->body;
        $this->assertStringContainsString('déclenchée par 2 événements', $body);
        $this->assertStringContainsString('Produit créé : Perceuse 18V (PRC18)', $body);
        $this->assertStringContainsString('Produit créé : Marteau (MRT01)', $body);
        $this->assertStringContainsString('— État des agents —', $body);
        $this->assertSame('ok', $routine->fresh()->last_status);
        $this->assertNull($routine->fresh()->next_run_at);                                  // pas d'horaire pour une routine à l'événement

        $this->assertSame(0, $runner->runDue());                                            // les mêmes événements ne reservent pas deux fois
        Product::factory()->create(['p_title' => 'Nouveau', 'p_sku' => 'NEW1']);
        $this->assertSame(0, $runner->runDue());                                            // dans le délai : il s'accumule

        $routine->update(['last_run_at' => now()->subMinutes(20)]);
        $this->assertSame(1, $runner->runDue());                                            // délai écoulé : il part, seul, au prochain passage
        $this->assertStringContainsString('déclenchée par un événement', OrchestratorMessage::where('role', 'orchestrator')->latest('id')->firstOrFail()->body);
    }

    public function test_conditions_filter_the_events_and_a_non_matching_event_is_skipped_for_good(): void
    {
        $routine = $this->eventRoutine(['event_type' => 'stock_bas', 'conditions' => ['qty_lte' => 3]], ['etat']);
        AgentEvent::create(['type' => 'stock_bas', 'source' => 'erp', 'status' => 'done', 'payload' => ['qty' => 4, 'title' => 'A', 'sku' => 'A1']]);
        $runner = app(RoutineRunner::class);

        $this->assertSame(0, $runner->runDue());                                            // 4 > 3 : ne correspond pas
        $this->assertSame((int) AgentEvent::max('id'), (int) $routine->fresh()->last_event_id);   // et il ne sera pas relu à chaque passage

        AgentEvent::create(['type' => 'stock_bas', 'source' => 'erp', 'status' => 'done', 'payload' => ['qty' => 2, 'title' => 'B', 'sku' => 'B1']]);
        $this->assertSame(1, $runner->runDue());
    }

    public function test_the_triggering_events_are_given_to_a_recruited_agent_as_context(): void
    {
        $agent = Agent::create(['domain' => 'perso-veille', 'name' => 'Veille', 'kind' => 'custom', 'mission' => 'Signale les produits en stock bas.', 'scopes' => ['stock'], 'created_by' => $this->admin->id, 'is_active' => true, 'default_level' => 'approval']);
        $this->eventRoutine(['event_type' => 'stock_bas'], ["agent:{$agent->id}"]);
        Setting::set('messaging', 'anthropic_api_key', encrypt('sk-test-cle'));
        Setting::set('agents', 'orchestrator_ai_enabled', 'true');
        Http::swap(new Factory());
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'id' => 't1', 'name' => 'submit_report', 'input' => ['report' => 'Un produit est bas.', 'level' => 'attention', 'proposals' => []]]]])]);
        AgentEvent::create(['type' => 'stock_bas', 'source' => 'erp', 'status' => 'done', 'payload' => ['qty' => 2, 'title' => 'Perceuse 18V', 'sku' => 'PRC18', 'warehouse' => 'Dépôt Principal']]);

        $this->assertSame(1, app(RoutineRunner::class)->runDue());

        Http::assertSent(function (Request $req) {
            $content = $req->data()['messages'][0]['content'] ?? '';

            return str_contains($content, 'Événements qui ont déclenché cette exécution') && str_contains($content, 'Stock bas : Perceuse 18V (PRC18), 2 pièce(s) dans Dépôt Principal');
        });
    }

    public function test_pausing_stops_the_listening_and_resuming_ignores_the_events_missed_in_the_meantime(): void
    {
        $routine = $this->eventRoutine(['event_type' => 'produit_cree']);
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => "mets en pause la routine #{$routine->id}"])->assertCreated();
        $this->assertFalse($routine->fresh()->is_active);

        Product::factory()->create();                                                       // en pause : pas d'événement
        $this->assertSame(0, AgentEvent::where('type', 'produit_cree')->count());

        AgentEvent::create(['type' => 'produit_cree', 'source' => 'erp', 'status' => 'done', 'payload' => ['title' => 'Oublié', 'sku' => 'OLD']]);   // arrivé pendant la pause
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => "reprends la routine #{$routine->id}"])->assertCreated();

        $this->assertSame(0, app(RoutineRunner::class)->runDue());                          // l'événement d'avant la reprise est ignoré
        $this->assertSame((int) AgentEvent::max('id'), (int) $routine->fresh()->last_event_id);

        $list = $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => 'mes routines'])->json('reply.body');
        $this->assertStringContainsString('à chaque fois que : un produit est créé', $list);
    }
}
