<?php

namespace Tests\Feature\Api;

use App\Models\AgentEvent;
use App\Models\Category;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Huitième lot de lectures : promotions d'un produit, règles et seuils des agents, événements, notifications, appareils,
 * factures non envoyées, mode de paiement, livraisons par ville, entrepôts, catégories de trésorerie, listes de prix.
 * Lecture seule, sans modèle de langage.
 */
class OrchestratorOversightTest extends TestCase
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
        $this->atlas = ThirdPartner::factory()->create(['tp_title' => 'Client Atlas', 'tp_Role' => 'customer', 'tp_city' => 'Casablanca', 'tp_status' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function say(string $text): string
    {
        $body = $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply.body');
        Http::assertNothingSent();

        return str_replace(["\u{202f}", "\u{a0}"], ' ', $body);
    }

    private function product(string $title, string $sku): Product
    {
        return Product::factory()->create(['p_title' => $title, 'p_sku' => $sku, 'p_status' => true, 'p_salePrice' => 100, 'category_id' => Category::factory()->create()->id]);
    }

    private function doc(string $type, string $status, string $day, float $ttc, array $footer = [], array $header = []): DocumentHeader
    {
        $d = DocumentHeader::factory()->create(array_merge(['document_type' => $type, 'status' => $status, 'issued_at' => $day, 'thirdPartner_id' => $this->atlas->id, 'reference' => 'D-' . fake()->unique()->numerify('####')], $header));
        DocumentFooter::factory()->create(array_merge(['document_header_id' => $d->id, 'total_ht' => round($ttc / 1.2, 2), 'total_ttc' => $ttc], $footer));

        return $d;
    }

    public function test_promotions_of_a_product_and_products_of_a_promotion(): void
    {
        $p = $this->product('Perceuse 18V', 'PRC18');
        $q = $this->product('Marteau', 'MRT1');
        $promo = DB::table('promotions')->insertGetId(['name' => 'Rentrée', 'slug' => 'rentree', 'type' => 'percentage', 'value' => 15, 'starts_at' => '2026-10-01 00:00:00', 'ends_at' => '2026-10-31 00:00:00', 'is_active' => true, 'priority' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $old = DB::table('promotions')->insertGetId(['name' => 'Été', 'slug' => 'ete', 'type' => 'fixed_amount', 'value' => 20, 'starts_at' => '2026-07-01 00:00:00', 'ends_at' => '2026-07-31 00:00:00', 'is_active' => false, 'priority' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('promotion_product')->insert([
            ['promotion_id' => $promo, 'product_id' => $p->id, 'promo_price' => 85, 'created_at' => now(), 'updated_at' => now()],
            ['promotion_id' => $promo, 'product_id' => $q->id, 'promo_price' => null, 'created_at' => now(), 'updated_at' => now()],
            ['promotion_id' => $old, 'product_id' => $p->id, 'promo_price' => 80, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $products = $this->say('produits de la promotion rentrée');
        $this->assertStringContainsString('Promotion « Rentrée » — 15 % jusqu\'au 31/10/2026', $products);
        $this->assertStringContainsString('2 produit(s)', $products);
        $this->assertStringContainsString('Perceuse 18V (PRC18) — 100,00 MAD → 85,00 MAD', $products);
        $this->assertStringContainsString('Je ne trouve aucune promotion', $this->say('produits de la promotion inexistante'));

        $promos = $this->say('promotions du produit PRC18');
        $this->assertStringContainsString('Rentrée — active, prix promo 85,00 MAD', $promos);
        $this->assertStringContainsString('Été — inactive, prix promo 80,00 MAD', $promos);
        $this->product('Sans promo', 'SP1');
        $this->assertStringContainsString('Aucune promotion ne le concerne', $this->say('promotions du produit SP1'));
    }

    public function test_routing_rules_thresholds_events_notifications_and_devices(): void
    {
        $rules = $this->say('règles de routage');
        $this->assertStringContainsString((string) DB::table('agent_routing_rules')->count() . ' règle(s) de routage', $rules);
        $this->assertStringContainsString('→ agent', $rules);

        $thresholds = $this->say('seuils des agents');
        $this->assertStringContainsString((string) DB::table('agent_thresholds')->count() . ' seuil(s) des agents', $thresholds);

        AgentEvent::create(['type' => 'document_depose', 'source' => 'orchestrator', 'status' => 'routed', 'payload' => ['text' => 'x']]);
        AgentEvent::create(['type' => 'document_depose', 'source' => 'orchestrator', 'status' => 'done', 'payload' => ['text' => 'x']]);
        $events = $this->say('événements des agents du mois');
        $this->assertStringContainsString('document_depose — 2 (routed 1, done 1)', $events);

        $notif = fn (string $title, ?string $readAt, int $userId) => DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\LowStock', 'notifiable_type' => User::class, 'notifiable_id' => $userId, 'data' => json_encode(['title' => $title]), 'read_at' => $readAt, 'created_at' => now(), 'updated_at' => now()]);
        $notif('Stock bas : Perceuse', null, $this->admin->id);
        $notif('Déjà lue', now(), $this->admin->id);
        $notif('Pour un autre', null, User::factory()->create()->id);
        $mine = $this->say('mes notifications non lues');
        $this->assertStringContainsString('1 notification(s) non lue(s)', $mine);
        $this->assertStringContainsString('Stock bas : Perceuse', $mine);
        $this->assertStringNotContainsString('Déjà lue', $mine);
        $this->assertStringNotContainsString('Pour un autre', $mine);

        DB::table('push_subscriptions')->insert(['subscribable_type' => User::class, 'subscribable_id' => $this->admin->id, 'endpoint' => 'https://push.example/SECRET-ENDPOINT', 'public_key' => 'SECRET-KEY', 'auth_token' => 'SECRET-AUTH', 'content_encoding' => 'aesgcm', 'created_at' => now(), 'updated_at' => now()]);
        $devices = $this->say('appareils abonnés aux notifications');
        $this->assertStringContainsString('1 appareil(s) abonné(s)', $devices);
        $this->assertStringContainsString('Karim Admin — 1 appareil(s)', $devices);
        $this->assertStringNotContainsString('SECRET', $devices);
    }

    public function test_unsent_invoices_payment_mix_and_deliveries_by_city(): void
    {
        $this->doc('InvoiceSale', 'confirmed', '2026-10-01', 1200, ['is_sent' => false, 'payment_method' => 'cash'], ['reference' => 'FV-NOSEND']);
        $this->doc('InvoiceSale', 'confirmed', '2026-10-02', 600, ['is_sent' => true, 'payment_method' => 'cheque'], ['reference' => 'FV-SENT']);
        $this->doc('InvoiceSale', 'draft', '2026-10-03', 999, ['is_sent' => false, 'payment_method' => 'cash'], ['reference' => 'FV-DRAFT']);
        $this->doc('InvoiceSale', 'confirmed', '2026-05-01', 700, ['is_sent' => false, 'payment_method' => 'cash'], ['reference' => 'FV-OLD']);

        $unsent = $this->say('factures non envoyées');
        $this->assertStringContainsString('1 facture(s) des 60 derniers jours non envoyée(s)', $unsent);
        $this->assertStringContainsString('FV-NOSEND', $unsent);
        $this->assertStringNotContainsString('FV-SENT', $unsent);
        $this->assertStringNotContainsString('FV-DRAFT', $unsent);
        $this->assertStringNotContainsString('FV-OLD', $unsent);

        $mix = $this->say('répartition des ventes par mode de paiement');
        $this->assertStringContainsString('espèces — 1 vente(s), 1 200,00 MAD (67 %)', $mix);
        $this->assertStringContainsString('chèque — 1 vente(s), 600,00 MAD (33 %)', $mix);

        $this->doc('DeliveryNote', 'delivered', '2026-10-05', 100, [], ['ship_city' => 'Rabat']);       // déjà livré : exclu
        $this->doc('DeliveryNote', 'confirmed', '2026-10-06', 100, [], ['ship_city' => 'Rabat']);
        $this->doc('CustomerOrder', 'confirmed', '2026-10-07', 100, [], ['ship_city' => null]);         // ville du client
        $this->doc('DeliveryNote', 'confirmed', '2026-10-08', 100, [], ['ship_city' => 'Rabat']);
        $cities = $this->say('livraisons par ville');
        $this->assertStringContainsString('3 livraison(s) ou commande(s) en attente', $cities);
        $this->assertStringContainsString('• Rabat — 2', $cities);
        $this->assertStringContainsString('• Casablanca — 1', $cities);
    }

    public function test_warehouses_cash_categories_and_price_lists(): void
    {
        $main = Warehouse::factory()->create(['wh_title' => 'Dépôt principal', 'wh_code' => 'WH1', 'wh_status' => true]);
        Warehouse::factory()->create(['wh_title' => 'Ancien dépôt', 'wh_status' => false]);
        $p = $this->product('Perceuse', 'P1');
        WarehouseHasStock::factory()->create(['warehouse_id' => $main->id, 'product_id' => $p->id, 'stockLevel' => 10, 'wh_average' => 30]);
        $wh = $this->say('mes entrepôts');
        $this->assertStringContainsString('Dépôt principal (WH1) — 1 produit(s) en stock, 10 pièce(s), 300,00 MAD', $wh);
        $this->assertStringContainsString('Ancien dépôt', $wh);
        $this->assertStringContainsString('— inactif', $wh);

        $loyer = DB::table('cash_categories')->insertGetId(['cc_title' => 'Loyer', 'cc_code' => 'LOY', 'cc_direction' => 'out', 'cc_status' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('cash_categories')->insert(['cc_title' => 'Divers', 'cc_code' => 'DIV', 'cc_direction' => 'both', 'cc_status' => false, 'created_at' => now(), 'updated_at' => now()]);
        $account = DB::table('cash_accounts')->insertGetId(['ca_title' => 'Caisse', 'ca_code' => 'C1', 'ca_type' => 'cash', 'ca_initial_balance' => 0, 'ca_status' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('cash_transactions')->insert(['ct_code' => 'T1', 'cash_account_id' => $account, 'cash_category_id' => $loyer, 'ct_direction' => 'out', 'ct_amount' => 3000, 'ct_date' => '2026-10-02', 'ct_label' => 'Loyer', 'ct_status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $cats = $this->say('mes catégories de trésorerie');
        $this->assertStringContainsString('Loyer (sorties) — 1 opération(s), 3 000,00 MAD', $cats);
        $this->assertStringContainsString('Divers (entrées et sorties) — inactive — 0 opération(s)', $cats);

        $list = DB::table('price_lists')->insertGetId(['name' => 'Revendeur', 'channel' => 'all', 'is_default' => false, 'is_active' => true, 'priority' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('price_list_items')->insert(['price_list_id' => $list, 'product_id' => $p->id, 'price_ht' => 80, 'price_ttc' => 96, 'min_qty' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('third_partners')->where('id', $this->atlas->id)->update(['price_list_id' => $list]);
        $this->assertStringContainsString('Revendeur (all) — 1 produit(s), 1 client(s) rattaché(s)', $this->say('mes listes de prix'));
    }

    public function test_orders_and_earlier_commands_are_not_swallowed(): void
    {
        $this->assertStringContainsString('agent stocks', mb_strtolower($this->say('prépare un inventaire')));
        $this->assertStringContainsString('Ventes', $this->say("chiffre d'affaires du mois"));
        $this->assertStringContainsString("Aucune liste de prix n'est définie.", $this->say('produits absents de la liste de prix revendeur'));   // lot 4, inchangé : ce n'est pas l'aperçu des listes
        $this->assertStringContainsString('Aucun stock en entrepôt', $this->say('valeur du stock'));                                          // lot 2, inchangé
    }
}
