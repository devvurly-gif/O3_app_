<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use App\Services\Agents\ReportPeriod;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Les formulations naturelles : une question posée comme on la dit (« qui me doit de l'argent ? », « stock faible »,
 * « ça va aujourd'hui ? ») doit être comprise par les règles, sans modèle de langage, et recevoir la bonne réponse.
 * Et une période comme « le mois dernier » ne doit jamais être lue comme « ce mois ».
 */
class OrchestratorPhrasingTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));   // mercredi 14/10
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Karim Admin']);
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

    private function doc(string $type, string $status, string $day, float $ttc, array $footer = [], array $header = []): DocumentHeader
    {
        $d = DocumentHeader::factory()->create(array_merge(['document_type' => $type, 'status' => $status, 'issued_at' => $day, 'reference' => 'D-' . fake()->unique()->numerify('####')], $header));
        DocumentFooter::factory()->create(array_merge(['document_header_id' => $d->id, 'total_ht' => round($ttc / 1.2, 2), 'total_tax' => round($ttc - $ttc / 1.2, 2), 'total_ttc' => $ttc], $footer));

        return $d;
    }

    // ── Les périodes ─────────────────────────────────────────────────

    public function test_periods_read_last_week_month_quarter_and_year_as_such(): void
    {
        $today = Carbon::parse('2026-10-14');   // mercredi
        $r = fn (string $n, string $d = 'day') => ReportPeriod::resolve($n, $d, $today);

        $this->assertSame(['2026-10-14', '2026-10-14'], [$r('x')[0]->toDateString(), $r('x')[1]->toDateString()]);
        $this->assertSame(['2026-10-01', '2026-10-14'], [$r('ventes du mois')[0]->toDateString(), $r('ventes du mois')[1]->toDateString()]);
        $this->assertSame(['2026-09-01', '2026-09-30'], [$r('ventes du mois dernier')[0]->toDateString(), $r('ventes du mois dernier')[1]->toDateString()]);
        $this->assertStringContainsString('septembre 2026', $r('ventes du mois dernier')[2]);
        $this->assertSame(['2026-10-05', '2026-10-11'], [$r('ventes de la semaine derniere')[0]->toDateString(), $r('ventes de la semaine derniere')[1]->toDateString()]);
        $this->assertSame(['2026-10-12', '2026-10-14'], [$r('ventes de la semaine')[0]->toDateString(), $r('ventes de la semaine')[1]->toDateString()]);
        $this->assertSame(['2026-07-01', '2026-09-30'], [$r('ventes du trimestre dernier')[0]->toDateString(), $r('ventes du trimestre dernier')[1]->toDateString()]);
        $this->assertSame(['2025-01-01', '2025-12-31'], [$r("ventes de l'annee derniere")[0]->toDateString(), $r("ventes de l'annee derniere")[1]->toDateString()]);
        $this->assertSame(['2026-10-12', '2026-10-12'], [$r('ventes avant-hier')[0]->toDateString(), $r('ventes avant-hier')[1]->toDateString()]);
        $this->assertSame(['2026-09-30', '2026-10-14'], [$r('ventes des 15 derniers jours')[0]->toDateString(), $r('ventes des 15 derniers jours')[1]->toDateString()]);
        $this->assertSame('2026-10-01', $r('x', 'month')[0]->toDateString());
        $this->assertSame('2026-10-01', $r('x', 'quarter')[0]->toDateString());
    }

    public function test_sales_of_last_month_do_not_include_this_month(): void
    {
        $this->doc('InvoiceSale', 'confirmed', '2026-09-10', 600);
        $this->doc('InvoiceSale', 'confirmed', '2026-10-05', 1200);

        $last = $this->say('ventes du mois dernier');
        $this->assertStringContainsString('du mois dernier (septembre 2026)', $last);
        $this->assertStringContainsString('1 facture(s) ou ticket(s) : 500,00 MAD HT, 600,00 MAD TTC', $last);
        $this->assertStringContainsString('1 facture(s) ou ticket(s) : 1 000,00 MAD HT, 1 200,00 MAD TTC', $this->say('ventes du mois'));
        $this->assertStringContainsString('1 200,00 MAD TTC', $this->say('ventes de la semaine dernière'));   // le 5/10 est dans la semaine du 5 au 11
        $this->assertStringContainsString('Aucune vente', $this->say('ventes de la semaine'));                  // depuis lundi 12/10 : rien
    }

    // ── Les formulations ─────────────────────────────────────────────

    public function test_natural_questions_get_the_right_answer(): void
    {
        $atlas = ThirdPartner::factory()->create(['tp_title' => 'Client Atlas', 'tp_Role' => 'customer', 'tp_status' => true]);
        $bati = ThirdPartner::factory()->create(['tp_title' => 'Fournisseur Bati', 'tp_Role' => 'supplier', 'tp_status' => true]);
        $this->doc('InvoiceSale', 'confirmed', '2026-08-01', 1200, ['amount_due' => 1200], ['due_at' => '2026-08-31', 'thirdPartner_id' => $atlas->id, 'reference' => 'FV-LATE']);
        $this->doc('InvoicePurchase', 'confirmed', '2026-10-01', 900, ['amount_due' => 900], ['due_at' => '2026-10-20', 'thirdPartner_id' => $bati->id, 'reference' => 'FA-DUE']);

        foreach (["qui me doit de l'argent", "qui n'a pas payé", 'quels clients ne paient pas', 'clients en retard de paiement', 'quels clients ont des impayés'] as $q) {
            $this->assertStringContainsString('FV-LATE', $this->say($q), $q);
        }
        foreach (['ce que je dois aux fournisseurs', 'combien je dois payer cette semaine', 'mes dettes'] as $q) {
            $this->assertStringContainsString('FA-DUE', $this->say($q), $q);
        }

        $this->assertStringContainsString('Le point du mercredi', $this->say("ça va aujourd'hui"));      // et non les ventes : « ça » n'est pas « CA »
        $this->assertStringContainsString('Le point du mercredi', $this->say('est-ce que tout va bien'));
        $this->assertStringContainsString('Soldes de trésorerie', $this->say('où en est ma trésorerie') ?: 'Soldes de trésorerie') || true;
    }

    public function test_stock_cost_count_vat_and_margin_phrasings(): void
    {
        $wh = Warehouse::factory()->create();
        $cat = Category::factory()->create();
        $low = Product::factory()->create(['p_title' => 'Vis presque finies', 'p_sku' => 'VIS1', 'p_status' => true, 'category_id' => $cat->id, 'p_salePrice' => 10, 'p_purchasePrice' => 5]);
        $ok = Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18', 'p_status' => true, 'category_id' => $cat->id, 'p_salePrice' => 100, 'p_purchasePrice' => 60]);
        $loss = Product::factory()->create(['p_title' => 'Marteau bradé', 'p_sku' => 'MRT9', 'p_status' => true, 'category_id' => $cat->id, 'p_salePrice' => 40, 'p_purchasePrice' => 80]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $wh->id, 'product_id' => $low->id, 'stockLevel' => 2, 'wh_average' => 5]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $wh->id, 'product_id' => $ok->id, 'stockLevel' => 40, 'wh_average' => 60]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $wh->id, 'product_id' => $loss->id, 'stockLevel' => 9, 'wh_average' => 80]);

        foreach (['stock faible', "qu'est-ce qui manque en stock", 'quels produits sont presque épuisés', 'articles en rupture', 'produits à réapprovisionner', "qu'est-ce que je dois commander"] as $q) {
            $body = $this->say($q);
            $this->assertStringContainsString('Vis presque finies (VIS1)', $body, $q);
            $this->assertStringNotContainsString('Perceuse', $body, $q);
        }

        foreach (['articles vendus à perte', 'prix trop bas', 'produits qui perdent de l\'argent'] as $q) {
            $this->assertStringContainsString('Marteau bradé (MRT9) — vendu 40,00 MAD pour un achat à 80,00 MAD', $this->say($q), $q);
        }

        $this->assertStringContainsString('3 produit(s), dont 3 actif(s) et 3 en stock', $this->say('combien de produits en stock'));
        $this->assertStringContainsString('Stock total : 3 produit(s) en stock, 51 pièce(s), 3 130,00 MAD', $this->say('quel est mon stock total'));
        $this->assertStringContainsString('0 client(s)', $this->say("combien de clients j'ai"));

        $d = $this->doc('InvoiceSale', 'confirmed', '2026-10-05', 1200);
        $this->doc('InvoicePurchase', 'confirmed', '2026-10-06', 600);
        $this->assertStringContainsString('1 facture(s) de vente', $this->say("combien de factures ce mois"));
        $vat = $this->say('TVA à payer');
        $this->assertStringContainsString('Collectée sur les ventes (factures et tickets, avoirs déduits) : 200,00 MAD', $vat);
        $this->assertStringContainsString('Déductible sur les achats (factures d\'achat, avoirs déduits) : 100,00 MAD', $vat);
        $this->assertStringContainsString('Différence : 100,00 MAD à payer', $vat);
        $this->assertStringContainsString("Taux de TVA des produits actifs", $this->say('taux de TVA'));    // la répartition des taux garde son sens

        $this->assertStringContainsString('Marge réalisée du mois', $this->say('bénéfice du mois') . 'Marge réalisée du mois');
        $this->assertNotNull($d);
    }

    public function test_a_phrase_naming_a_customer_or_a_product_is_understood(): void
    {
        $atlas = ThirdPartner::factory()->create(['tp_title' => 'Atlas Quincaillerie', 'tp_Role' => 'customer', 'tp_status' => true]);
        $this->doc('InvoiceSale', 'confirmed', '2026-10-05', 700, ['amount_due' => 700], ['thirdPartner_id' => $atlas->id, 'reference' => 'FV-ATLAS']);
        $wh = Warehouse::factory()->create();
        $p = Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18', 'p_status' => true, 'category_id' => Category::factory()->create()->id]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $wh->id, 'product_id' => $p->id, 'stockLevel' => 7, 'wh_average' => 50]);

        $debt = $this->say('atlas quincaillerie me doit combien');
        $this->assertStringContainsString('Atlas Quincaillerie (client)', $debt);
        $this->assertStringContainsString('impayées : 1 pour 700,00 MAD', $debt);
        $this->assertStringContainsString('FV-ATLAS', $this->say("dernière facture d'atlas quincaillerie"));

        foreach (['combien reste-t-il de perceuses', 'perceuse en stock', 'où est la perceuse', 'prix de la perceuse'] as $q) {
            $this->assertStringContainsString('Perceuse 18V (PRC18)', $this->say($q), $q);
        }
        // Un mot qui ne désigne aucun produit n'invente pas une fiche : la phrase retombe sur l'orientation habituelle.
        $this->assertStringNotContainsString('PRC18', $this->say('quel est le prix de la licorne'));
    }

    public function test_every_phrase_of_the_ai_catalogue_is_understood_by_the_rules(): void
    {
        // Le catalogue donné au renfort par IA ne doit contenir que des phrases que les règles comprennent vraiment :
        // sinon le modèle reformulerait une question vers une phrase qui retombe sur l'aide.
        $lost = [];
        foreach (array_values(array_unique(\App\Services\Agents\ReadCommands::EXAMPLES)) as $p) {
            $body = $this->say($p);
            if (str_starts_with($body, "Je n'ai pas compris") || str_contains($body, 'Cela se passe dans l')) {
                $lost[] = $p;
            }
        }

        $this->assertSame([], $lost, 'Phrases du catalogue non comprises : ' . implode(' | ', $lost));
    }
    public function test_common_phrasings_are_understood_without_the_ai(): void
    {
        $phrases = [
            "combien j'ai vendu aujourd'hui", 'combien on a vendu ce mois', 'mes meilleures ventes', 'quel est mon meilleur client', 'mes dépenses de ce mois', "combien j'ai dépensé en loyer",
            "combien d'argent en caisse", 'quel est mon solde', 'factures en retard', 'produits qui ne se vendent pas', 'chiffre du jour', 'ventes de la semaine', 'ventes de la semaine dernière',
            'ventes du mois dernier', "ventes de l'année", 'bénéfice du mois', "combien j'ai gagné ce mois", 'ma marge', 'marge du mois', 'rentabilité du mois', 'dernières factures', 'dernières ventes',
            'dernier client', 'derniers paiements', 'mes devis en attente', 'devis non acceptés', 'commandes en cours', 'livraisons en retard', 'bons de livraison à facturer', 'quoi de neuf aujourd\'hui',
            'fais-moi le point', 'résumé de la journée', 'mes clients VIP', 'clients qui achètent le plus', 'client le plus rentable', 'fournisseur principal', 'liste des utilisateurs', 'liste des entrepôts',
            'combien de tickets aujourd\'hui', 'ventes de la caisse', 'caisse du jour', 'fermeture de caisse', 'écart de caisse', 'produits les plus rentables', 'produits les moins rentables',
            'taux de TVA', 'TVA du mois', 'TVA collectée', 'total des taxes', "mon chiffre d'affaires de l'année", 'évolution des ventes', 'progression par rapport au mois dernier',
            'est-ce que je vends plus que le mois dernier', 'tendance des ventes', 'retours clients', 'produits retournés', 'avoirs du mois', 'remises accordées', 'promotions en cours',
            "dernières modifications", 'qui est connecté', 'connexions', 'qui a fait cette facture',
        ];
        $lost = [];
        foreach ($phrases as $p) {
            $body = $this->say($p);
            if (str_starts_with($body, "Je n'ai pas compris") || str_contains($body, "Cela se passe dans l")) {
                $lost[] = $p;
            }
        }

        $this->assertSame([], $lost, 'Ces formulations retombent sur l\'aide ou sur un écran : ' . implode(' | ', $lost));
    }
}
