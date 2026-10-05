<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\AgentEvent;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\OrchestratorMessage;
use App\Models\PaymentReminder;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Session administrateur ↔ orchestrateur : il comprend une liste de demandes,
 * les confie aux agents, et ne déclenche un ordre que sur un verbe d'action.
 */
class OrchestratorTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Admin Test']);
    }

    private function say(string $text, ?User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text]);
    }

    private function body(string $text): string
    {
        return $this->say($text)->assertCreated()->json('reply.body');
    }

    private function stock(string $sku, Warehouse $w, float $level): Product
    {
        $p = Product::factory()->create(['p_sku' => $sku, 'p_title' => "Article {$sku}", 'p_status' => true]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $w->id, 'product_id' => $p->id, 'stockLevel' => $level]);

        return $p;
    }

    // ── Compréhension ────────────────────────────────────────────────

    public function test_a_greeting_gets_the_help_and_an_unknown_request_says_it_was_not_understood(): void
    {
        $hello = $this->say('Bonjour')->assertCreated();
        $this->assertStringContainsString('Je suis l\'orchestrateur', $hello->json('reply.body'));
        $this->assertStringContainsString('prépare un inventaire', $hello->json('reply.body'));

        $unknown = $this->say('quelle est la capitale du Maroc ?')->assertCreated();
        $this->assertStringContainsString("Je n'ai pas compris", $unknown->json('reply.body'));
        $this->assertStringContainsString('contrôle les encaissements', $unknown->json('reply.body'));
        $this->assertSame(0, AgentEvent::count());   // comprendre ou non, rien n'est déclenché
    }

    public function test_a_named_screen_is_described_and_linked_and_a_modification_is_not_pretended(): void
    {
        // Les fiches produits ont leur agent (voir OrchestratorCatalogTest) ; on cherche ici seulement leur écran.
        $screen = $this->say('la page des fiches produits')->assertCreated()->json('reply');
        $this->assertSame('/products', $screen['links'][0]['to']);

        // Demande de modification sans agent : on le dit franchement et on envoie à l'écran.
        $prices = $this->say('modifier les prix')->assertCreated()->json('reply');
        $this->assertStringContainsString("aucun agent n'en est chargé", $prices['body']);
        $this->assertSame('/settings/bulk-prices', $prices['links'][0]['to']);

        // Simple question de localisation : on oriente, sans prétendre ne pas savoir faire.
        $where = $this->say('où créer une facture ?')->assertCreated()->json('reply');
        $this->assertStringStartsWith("Cela se passe dans l'écran", $where['body']);
        $this->assertSame('/ventes/documents/create', $where['links'][0]['to']);

        $this->assertSame('/products/trashed', $this->say('la corbeille')->json('reply.links.0.to'));
        $this->assertSame(0, AgentEvent::count());   // orientation seule : rien n'est déclenché
    }

    public function test_what_can_be_done_in_the_app_lists_every_domain_and_says_what_agents_can_run(): void
    {
        $body = $this->say('que peut-on faire dans O3 ?')->assertCreated()->json('reply.body');

        foreach (['Catalogue', 'Ventes', 'Achats', 'Stock', 'Finances', 'Administration', 'Agents'] as $domain) {
            $this->assertStringContainsString("• {$domain} :", $body);
        }
        $this->assertStringContainsString('Produits', $body);
        $this->assertStringContainsString('Révision des prix', $body);
        $this->assertStringContainsString('préparer un inventaire, contrôler les encaissements', $body);
    }

    public function test_every_screen_in_the_catalog_points_to_a_real_frontend_route(): void
    {
        $router = file_get_contents(base_path('resources/js/router/index.ts'));

        foreach (\App\Services\Agents\AppCatalog::screens() as $key => $screen) {
            $this->assertStringContainsString("path: '{$screen['path']}'", $router, "L'écran « {$key} » pointe vers une route inexistante.");
        }
    }

    public function test_the_help_lists_the_navigation_requests(): void
    {
        $this->assertStringContainsString('que peut-on faire dans O3', $this->say('aide')->json('reply.body'));
    }

    public function test_status_summarises_agents_events_and_pending_validations(): void
    {
        AgentEvent::create(['source' => 'whatsapp', 'status' => 'to_sort', 'payload' => ['text' => 'bonjour']]);
        AgentEvent::create(['source' => 'whatsapp', 'status' => 'rejected', 'payload' => ['text' => 'PIN incorrect']]);
        PaymentReminder::create(['document_header_id' => 1, 'level' => 1, 'message' => 'x', 'amount_due' => 1200, 'days_overdue' => 5, 'status' => 'draft']);

        $r = $this->say('état des agents')->assertCreated();

        $body = $r->json('reply.body');
        $this->assertStringContainsString('Agents actifs : Achats & catalogue, Ventes & CRM, Stocks, Expédition', $body);
        $this->assertStringContainsString('Recouvrement', $body);                       // listé parmi les inactifs
        $this->assertStringContainsString('Routeur de messages : désactivé', $body);
        $this->assertStringContainsString('2 au total, 1 à trier, 1 refusé', $body);
        $this->assertStringContainsString('Relances à valider : 1 (1 200,00 MAD)', $body);
        $this->assertContains('/settings/agents', array_column($r->json('reply.links'), 'to'));
    }

    public function test_status_tells_when_the_foundation_was_never_seeded(): void
    {
        Agent::query()->delete();

        $r = $this->say('état des agents')->assertCreated();

        $this->assertTrue($r->json('reply.error'));
        $this->assertStringContainsString('seeder', $r->json('reply.body'));
    }

    public function test_to_sort_lists_the_latest_unrouted_events(): void
    {
        $this->assertStringContainsString('Rien à trier', $this->body('événements à trier'));

        AgentEvent::create(['source' => 'sms', 'status' => 'to_sort', 'payload' => ['text' => 'message inconnu']]);

        $body = $this->body('quels sont les événements à trier ?');
        $this->assertStringContainsString('1 événement(s) à trier', $body);
        $this->assertStringContainsString('message inconnu', $body);
    }

    // ── Inventaire ───────────────────────────────────────────────────

    public function test_asking_about_the_inventory_without_an_action_verb_triggers_nothing(): void
    {
        $body = $this->body('et l\'inventaire ?');

        $this->assertStringContainsString("Feuilles d'inventaire : 0 préparée(s)", $body);
        $this->assertSame(0, AgentEvent::count());
    }

    public function test_an_inventory_order_goes_to_the_stocks_agent_and_prepares_a_sheet(): void
    {
        $main = Warehouse::factory()->create(['wh_title' => 'Dépôt Principal', 'wh_status' => true]);
        $this->stock('NEG-1', $main, -2);
        $this->stock('OK-1', $main, 9);

        $r = $this->say('prépare un inventaire')->assertCreated();

        $event = AgentEvent::sole();
        $this->assertSame('inventaire_demande', $event->type);
        $this->assertSame('stocks', $event->agent->domain);
        $this->assertSame('Admin Test', $event->payload['ordered_by']);
        $this->assertStringContainsString('Feuille prête : 2 article(s)', $r->json('reply.body'));
        $this->assertStringContainsString('Rien n\'est modifié', $r->json('reply.body'));
        $this->assertSame($event->id, $r->json('reply.event_id'));
        $this->assertContains('/stock/inventaire', array_column($r->json('reply.links'), 'to'));
        $this->assertEquals(-2, WarehouseHasStock::where('product_id', Product::where('p_sku', 'NEG-1')->value('id'))->value('stockLevel'));
    }

    public function test_the_inventory_order_understands_a_warehouse_name_and_the_attention_scope(): void
    {
        $main = Warehouse::factory()->create(['wh_title' => 'Dépôt Principal', 'wh_status' => true]);
        $shop = Warehouse::factory()->create(['wh_title' => 'Magasin Centre', 'wh_status' => true]);
        $this->stock('A-1', $main, 5);
        $this->stock('B-1', $shop, -1);   // à vérifier
        $this->stock('B-2', $shop, 4);

        $onlyShop = $this->say('lance un inventaire du magasin centre')->assertCreated();
        $this->assertStringContainsString('« Magasin Centre »', $onlyShop->json('reply.body'));
        $this->assertStringContainsString('2 article(s)', $onlyShop->json('reply.body'));

        $attention = $this->say('prépare un inventaire des articles à vérifier')->assertCreated();
        $this->assertStringContainsString('tous les entrepôts', $attention->json('reply.body'));
        $this->assertStringContainsString('3 article(s), dont 3 à vérifier', $attention->json('reply.body'));
    }

    public function test_an_inventory_order_is_refused_clearly_when_the_agent_is_inactive(): void
    {
        Agent::where('domain', 'stocks')->update(['is_active' => false]);

        $r = $this->say('prépare un inventaire')->assertCreated();

        $this->assertTrue($r->json('reply.error'));
        $this->assertStringContainsString('inactif', $r->json('reply.body'));
    }

    // ── Encaissements ────────────────────────────────────────────────

    private function overdueInvoice(int $daysLate, float $ttc = 1000.0): DocumentHeader
    {
        $client = ThirdPartner::factory()->customer()->create(['tp_title' => 'Quincaillerie Atlas']);
        $doc = DocumentHeader::factory()->create([
            'document_type' => 'InvoiceSale', 'thirdPartner_id' => $client->id, 'status' => 'pending',
            'issued_at' => now()->subDays($daysLate + 30), 'due_at' => now()->subDays($daysLate),
        ]);
        DocumentFooter::create(['document_header_id' => $doc->id, 'total_ht' => $ttc / 1.2, 'total_tax' => $ttc - $ttc / 1.2, 'total_ttc' => $ttc, 'amount_paid' => 0, 'amount_due' => $ttc]);

        return $doc;
    }

    public function test_asking_about_reminders_only_reads_and_never_orders(): void
    {
        $this->overdueInvoice(5);
        Agent::where('domain', 'recouvrement')->update(['is_active' => true]);

        $this->assertStringContainsString("Aucune relance n'attend", $this->body('les relances ?'));
        $this->assertSame(0, PaymentReminder::count());
        $this->assertSame(0, AgentEvent::count());
    }

    public function test_a_collections_order_prepares_reminders_and_sends_nothing(): void
    {
        Agent::where('domain', 'recouvrement')->update(['is_active' => true]);
        $this->overdueInvoice(5, 2400.0);

        $r = $this->say('contrôle les encaissements')->assertCreated();

        $this->assertSame('controle_encaissements', AgentEvent::sole()->type);
        $this->assertSame(1, PaymentReminder::where('status', 'draft')->count());
        $this->assertStringContainsString('1 facture(s) en retard', $r->json('reply.body'));
        $this->assertStringContainsString('1 relance(s) préparée(s) en brouillon', $r->json('reply.body'));
        $this->assertStringContainsString("Aucun message n'est parti", $r->json('reply.body'));

        $list = $this->body('relances à valider');
        $this->assertStringContainsString('1 relance(s) attendent votre validation', $list);
        $this->assertStringContainsString('Quincaillerie Atlas', $list);
        $this->assertStringContainsString('2 400,00 MAD', $list);
    }

    public function test_a_collections_order_is_refused_while_the_agent_is_inactive(): void
    {
        $r = $this->say('lance le contrôle des encaissements')->assertCreated();

        $this->assertTrue($r->json('reply.error'));
        $this->assertStringContainsString('inactif', $r->json('reply.body'));
        $this->assertSame(0, PaymentReminder::count());

        // L'ordre refusé est « refusé », pas un message à classer : il ne pollue ni « à trier » ni l'état.
        $this->assertStringContainsString('Rien à trier', $this->body('événements à trier'));
        $this->assertStringContainsString('0 à trier, 1 refusé', $this->body('état des agents'));
    }

    // ── Session administrateur ───────────────────────────────────────

    public function test_both_sides_of_the_exchange_are_kept_and_each_admin_has_their_own_session(): void
    {
        $other = User::factory()->admin()->create();

        $this->say('bonjour');
        $this->say('état des agents');
        $this->say('bonjour', $other);

        $mine = $this->actingAs($this->admin, 'sanctum')->getJson('/api/agents/orchestrateur')->assertOk()->json('messages');
        $this->assertSame(['admin', 'orchestrator', 'admin', 'orchestrator'], array_column($mine, 'role'));
        $this->assertSame('bonjour', $mine[0]['body']);

        $theirs = $this->actingAs($other, 'sanctum')->getJson('/api/agents/orchestrateur')->json('messages');
        $this->assertCount(2, $theirs);
    }

    public function test_clearing_the_session_only_removes_my_own_messages(): void
    {
        $other = User::factory()->admin()->create();
        $this->say('bonjour');
        $this->say('bonjour', $other);

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/agents/orchestrateur')->assertNoContent();

        $this->assertSame(0, OrchestratorMessage::where('user_id', $this->admin->id)->count());
        $this->assertSame(2, OrchestratorMessage::where('user_id', $other->id)->count());
    }

    public function test_clearing_the_session_does_not_cancel_orders_already_given(): void
    {
        $main = Warehouse::factory()->create(['wh_status' => true]);
        $this->stock('A-1', $main, 3);
        $this->say('prépare un inventaire');

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/agents/orchestrateur')->assertNoContent();

        $this->assertSame(1, AgentEvent::where('type', 'inventaire_demande')->count());
    }

    // ── Accès ────────────────────────────────────────────────────────

    public function test_only_administrators_with_an_interactive_session_can_talk_to_the_orchestrator(): void
    {
        foreach (['manager', 'cashier', 'warehouse'] as $role) {
            $u = User::factory()->{$role}()->create();
            $this->actingAs($u, 'sanctum')->getJson('/api/agents/orchestrateur')->assertForbidden();
            $this->actingAs($u, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => 'bonjour'])->assertForbidden();
        }

        $plain = $this->admin->createToken('agent', ['achats:import'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$plain}")->postJson('/api/agents/orchestrateur', ['message' => 'bonjour'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->postJson('/api/agents/orchestrateur', ['message' => 'bonjour'])->assertUnauthorized();
        $this->assertSame(0, OrchestratorMessage::count());
    }

    public function test_the_message_is_validated(): void
    {
        $this->say('')->assertStatus(422);
        $this->say(str_repeat('a', 1001))->assertStatus(422);
        $this->assertSame(0, OrchestratorMessage::count());
    }
}
