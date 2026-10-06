<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Septième lot de lectures : messagerie, importations, relances, dossiers des agents, droits, chèques, bannières,
 * terminaux, variantes, prix par liste ; et le renfort par IA qui reformule une question libre en commande de lecture.
 */
class OrchestratorExtrasTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $atlas;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Karim Admin']);
        $this->atlas = ThirdPartner::factory()->create(['tp_title' => 'Client Atlas', 'tp_Role' => 'customer', 'tp_status' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reply(string $text): array
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
    }

    private function say(string $text): string
    {
        $body = $this->reply($text)['body'];
        Http::assertNothingSent();

        return str_replace(["\u{202f}", "\u{a0}"], ' ', $body);
    }

    private function product(string $title, string $sku, array $over = []): Product
    {
        return Product::factory()->create(array_merge(['p_title' => $title, 'p_sku' => $sku, 'p_status' => true, 'p_salePrice' => 100, 'category_id' => Category::factory()->create()->id], $over));
    }

    public function test_messaging_orders_purchase_imports_reminders_and_agent_cases(): void
    {
        $now = now();
        $doc = DocumentHeader::factory()->create(['reference' => 'BL-0007', 'document_type' => 'DeliveryNote']);
        DB::table('order_messages')->insert([
            ['channel' => 'whatsapp', 'direction' => 'in', 'phone' => '+212612345678', 'third_partner_id' => $this->atlas->id, 'user_id' => null, 'body' => 'SECRET-CONTENU-CLIENT', 'status' => 'created', 'document_id' => $doc->id, 'created_at' => $now, 'updated_at' => $now],
            ['channel' => 'whatsapp', 'direction' => 'in', 'phone' => '+212699887766', 'third_partner_id' => null, 'user_id' => null, 'body' => 'inconnu', 'status' => 'unknown_sender', 'document_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['channel' => 'whatsapp', 'direction' => 'out', 'phone' => '+212612345678', 'third_partner_id' => $this->atlas->id, 'user_id' => null, 'body' => 'réponse', 'status' => 'sent', 'document_id' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $orders = $this->say('commandes WhatsApp du jour');
        $this->assertStringContainsString('2 message(s) (', $orders);
        $this->assertStringContainsString('created : 1', $orders);
        $this->assertStringContainsString('unknown_sender : 1', $orders);
        $this->assertStringContainsString('Client Atlas — created → BL-0007', $orders);
        $this->assertStringContainsString('client inconnu (…7766)', $orders);
        $this->assertStringNotContainsString('SECRET-CONTENU-CLIENT', $orders);
        $this->assertStringNotContainsString('612345678', $orders);

        DB::table('purchase_imports')->insert(['external_id' => 'x1', 'status' => 'created', 'payload_hash' => str_repeat('a', 64), 'payload' => '{}', 'response' => '{}', 'document_reference' => 'FA-0009', 'created_at' => $now, 'updated_at' => $now]);
        $this->assertStringContainsString('created : 1', $this->say("importations d'achat récentes"));

        $invoice = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale']);
        $reminder = fn (string $status, float $due, ?string $error = null, int $level = 1) => DB::table('payment_reminders')->insert(['document_header_id' => $invoice->id, 'third_partner_id' => $this->atlas->id, 'level' => $level, 'channel' => 'whatsapp', 'message' => 'm', 'amount_due' => $due, 'days_overdue' => 12, 'status' => $status, 'error' => $error, 'created_at' => $now, 'updated_at' => $now]);
        $reminder('sent', 1000);
        $reminder('failed', 500, 'numéro invalide', 2);
        $reminders = $this->say('relances de paiement du mois');
        $this->assertStringContainsString('sent — 1 relance(s), 1 000,00 MAD dus', $reminders);
        $this->assertStringContainsString('Client Atlas (whatsapp) — numéro invalide', $reminders);

        DB::table('agent_cases')->insert(['third_partner_id' => $this->atlas->id, 'status' => 'open', 'opened_at' => '2026-10-01 09:00:00', 'created_at' => $now, 'updated_at' => $now]);
        $cases = $this->say('dossiers ouverts des agents');
        $this->assertStringContainsString('1 dossier(s) ouvert(s)', $cases);
        $this->assertStringContainsString('Client Atlas — ouvert le 01/10/2026', $cases);
    }

    public function test_permissions_cheques_banners_terminals_variants_and_prices_by_list(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'gerant', 'display_name' => 'Gérant', 'description' => 'x', 'is_system' => false, 'created_at' => now(), 'updated_at' => now()]);
        $perm = fn (string $module, string $action) => DB::table('permissions')->insertGetId(['name' => "{$module}.{$action}", 'module' => $module, 'action' => $action, 'display_name' => "{$module} {$action}", 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$perm('ventes', 'voir'), $perm('ventes', 'creer'), $perm('stock', 'voir')] as $pid) {
            DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $pid, 'created_at' => now(), 'updated_at' => now()]);
        }
        $roles = $this->say('rôles et permissions');
        $this->assertStringContainsString('Gérant — 3 permission(s), 0 utilisateur(s)', $roles);
        $detail = $this->say('permissions du rôle gérant');
        $this->assertStringContainsString('a 3 permission(s)', $detail);
        $this->assertStringContainsString('ventes : creer, voir', $detail);

        $inv = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'reference' => 'FV-0100', 'thirdPartner_id' => $this->atlas->id]);
        Payment::factory()->create(['document_header_id' => $inv->id, 'amount' => 700, 'method' => 'cheque', 'reference' => 'CHQ-55', 'paid_at' => '2026-10-08']);
        Payment::factory()->create(['document_header_id' => $inv->id, 'amount' => 300, 'method' => 'cash', 'paid_at' => '2026-10-08']);
        $cheques = $this->say('chèques et effets reçus ce mois');
        $this->assertStringContainsString('1 paiement(s), 700,00 MAD (dont 700,00 MAD reçus de clients)', $cheques);
        $this->assertStringContainsString('chèque CHQ-55 — 700,00 MAD — FV-0100 (Client Atlas)', $cheques);
        $this->assertStringContainsString("n'enregistre pas la date d'échéance", $cheques);

        $slide = fn (string $title, bool $active, ?string $ends) => DB::table('slides')->insert(['title' => $title, 'image' => '/storage/s.jpg', 'link_type' => 'none', 'position' => 'hero', 'sort_order' => 1, 'starts_at' => '2026-10-01 00:00:00', 'ends_at' => $ends, 'is_active' => $active, 'created_at' => now(), 'updated_at' => now()]);
        $slide('Rentrée', true, '2026-10-31 00:00:00');
        $slide('Terminée', true, '2026-10-05 00:00:00');
        $slide('Coupée', false, null);
        $banners = $this->say('bannières actives');
        $this->assertStringContainsString('1 bannière(s) active(s)', $banners);
        $this->assertStringContainsString('Rentrée — hero', $banners);

        $wh = Warehouse::factory()->create(['wh_title' => 'Dépôt']);
        PosTerminal::factory()->create(['name' => 'Caisse 1', 'code' => 'POS1', 'warehouse_id' => $wh->id, 'is_active' => true]);
        $this->assertStringContainsString('Caisse 1 (POS1) — actif — entrepôt Dépôt', $this->say('terminaux de caisse'));

        $p = $this->product('Perceuse 18V', 'PRC18');
        DB::table('product_variants')->insert([
            ['product_id' => $p->id, 'label' => 'Rouge', 'attributes' => '{}', 'sku' => 'PRC18-R', 'price' => 100, 'stock' => 0, 'is_active' => true, 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $p->id, 'label' => 'Bleu', 'attributes' => '{}', 'sku' => 'PRC18-B', 'price' => 100, 'stock' => 4, 'is_active' => false, 'position' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->assertStringContainsString('Perceuse 18V (PRC18) — 2 variante(s), 1 sans stock, 1 inactive(s)', $this->say('produits avec variantes'));

        $list = DB::table('price_lists')->insertGetId(['name' => 'Revendeur', 'channel' => 'all', 'is_default' => false, 'is_active' => true, 'priority' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('price_list_items')->insert(['price_list_id' => $list, 'product_id' => $p->id, 'price_ht' => 80, 'price_ttc' => 96, 'min_qty' => 5, 'created_at' => now(), 'updated_at' => now()]);
        $prices = $this->say('prix du produit PRC18 par liste de prix');
        $this->assertStringContainsString('prix de vente de la fiche 100,00 MAD HT', $prices);
        $this->assertStringContainsString('Revendeur (all) — 80,00 MAD HT, 96,00 MAD TTC, dès 5 pièces', $prices);
    }

    private function enableAi(array ...$responses): void
    {
        Http::swap(new Factory());
        Setting::set('messaging', 'anthropic_api_key', encrypt('sk-test-cle'));
        Setting::set('agents', 'orchestrator_ai_enabled', 'true');
        $sequence = Http::sequence();
        foreach ($responses as $input) {
            $sequence->pushResponse(Http::response(['content' => [['type' => 'tool_use', 'id' => 't', 'name' => 'route_request', 'input' => $input]], 'stop_reason' => 'tool_use']));
        }
        Http::fake(['api.anthropic.com/*' => $sequence]);
    }

    public function test_a_free_question_is_rephrased_into_a_known_read_and_answered_from_the_database(): void
    {
        $d = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'status' => 'confirmed', 'issued_at' => '2026-10-13', 'thirdPartner_id' => $this->atlas->id]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => 500, 'total_ttc' => 600]);
        $this->enableAi(['intent' => 'lecture', 'phrase' => "ventes d'hier"]);

        $reply = $this->reply("comment s'est passée la veille côté recettes ?");

        $this->assertStringContainsString('1 facture(s) ou ticket(s)', $reply['body']);
        $this->assertTrue($reply['ai']);
        // Le modèle ne reçoit que la question et la liste des commandes : aucune donnée de l'entreprise.
        Http::assertSent(fn (Request $r) => str_contains($r['system'], "ventes d'hier") && !str_contains(json_encode($r->data()), 'Client Atlas'));
    }

    public function test_the_rephrased_phrase_is_only_used_when_the_rules_see_a_read_never_an_order(): void
    {
        $this->enableAi(['intent' => 'lecture', 'phrase' => 'applique le lot 3'], ['intent' => 'lecture', 'phrase' => 'supprime tous les clients'], ['intent' => 'lecture', 'phrase' => null]);

        foreach (['fais passer le truc 3', 'efface la base', 'dis-moi des choses'] as $text) {
            $body = $this->reply($text)['body'];
            $this->assertStringContainsString('Je n\'ai pas compris', $body);   // l'aide habituelle : rien n'a été exécuté
        }
        $this->assertSame(0, \App\Models\AgentEvent::where('status', 'done')->count());
    }

    public function test_orders_and_earlier_commands_are_not_swallowed(): void
    {
        $this->assertStringContainsString('agent stocks', mb_strtolower($this->say('prépare un inventaire')));
        $this->assertStringContainsString('Ventes', $this->say("chiffre d'affaires du mois"));
        $this->assertNotSame('extras', $this->reply('relances à valider')['intent'] ?? '');
        $this->assertStringContainsString('Jadever', $this->say('cherche les photos Jadever'));
    }
}
