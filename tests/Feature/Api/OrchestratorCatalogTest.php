<?php

namespace Tests\Feature\Api;

use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use App\Services\Agents\CatalogEnricher;
use App\Services\BulkSalePriceUpdater;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Mettre à jour les fiches produits » : contrôle en lecture seule, propositions (descriptions et
 * catégories par IA, prix par marge, photos manquantes) et application seulement au clic, sur ce qui manque
 * encore. Aucun appel réel à l'API : la réponse du modèle est simulée.
 */
class OrchestratorCatalogTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private Category $tools;
    private Category $uncategorized;
    private Product $complete;
    private Product $bare;
    private Product $cheap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->tools = Category::factory()->create(['ctg_title' => 'Outillage électroportatif']);
        $this->uncategorized = Category::factory()->create(['ctg_title' => 'Non catégorisé']);   // la base impose une catégorie : celle par défaut

        // Une fiche complète et cohérente, avec photo.
        $this->complete = Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18', 'p_description' => 'Perceuse sans fil 18 volts', 'p_purchasePrice' => 60, 'p_salePrice' => 100, 'category_id' => $this->tools->id]);
        ProductImage::create(['product_id' => $this->complete->id, 'url' => '/storage/products/a.jpg', 'title' => 'a', 'isPrimary' => true]);

        // Une fiche nue : pas de photo, description recopiée du titre, catégorie par défaut, pas de prix de vente.
        $this->bare = Product::factory()->create(['p_title' => 'Disque à tronçonner 115 mm', 'p_sku' => 'DISQ115', 'p_description' => 'Disque à tronçonner 115 mm', 'p_purchasePrice' => 50, 'p_salePrice' => 0, 'category_id' => $this->uncategorized->id]);

        // Une fiche vendue sous son prix d'achat.
        $this->cheap = Product::factory()->create(['p_title' => 'Marteau', 'p_sku' => 'MRT01', 'p_description' => 'Marteau de coffreur', 'p_purchasePrice' => 80, 'p_salePrice' => 40, 'category_id' => $this->tools->id]);
        ProductImage::create(['product_id' => $this->cheap->id, 'url' => '/storage/products/b.jpg', 'title' => 'b', 'isPrimary' => true]);

        // La révision des prix ne traite que les articles en stock (comme l'écran du même nom).
        $warehouse = Warehouse::factory()->create(['wh_status' => true]);
        foreach ([$this->bare, $this->cheap] as $p) {
            WarehouseHasStock::factory()->create(['warehouse_id' => $warehouse->id, 'product_id' => $p->id, 'stockLevel' => 5]);
        }
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

    private function modelCompletes(array $products): void
    {
        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'complete_products', 'input' => ['products' => $products]]]])]);
    }

    private function snapshot(): string
    {
        return md5(Product::orderBy('id')->get(['id', 'p_description', 'category_id', 'p_salePrice'])->toJson());
    }

    // ── Contrôle ─────────────────────────────────────────────────────

    public function test_the_audit_counts_each_problem_and_changes_nothing(): void
    {
        $before = $this->snapshot();

        $r = $this->say('mettre à jour les fiches produits');

        $body = $r->json('reply.body');
        $this->assertStringContainsString('Contrôle des fiches produits — 3 fiche(s)', $body);
        $this->assertStringContainsString('• Sans photo : 1', $body);
        $this->assertStringContainsString('• Sans description (vide ou identique au titre) : 1', $body);
        $this->assertStringContainsString('• Non catégorisées (catégorie par défaut) : 1', $body);
        $this->assertStringContainsString('• Sans prix de vente : 1', $body);
        $this->assertStringContainsString("• Prix de vente sous le prix d'achat : 1", $body);
        $this->assertStringContainsString('2 fiche(s) à corriger', $body);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame('done', AgentEvent::where('type', 'catalogue_controle')->firstOrFail()->status);
    }

    public function test_the_typo_from_the_chat_reaches_the_audit(): void
    {
        foreach (['  update fiche prouits ', 'mets à jour les fiches produits', 'contrôle les fiches produits', 'quelles fiches sont incomplètes ?'] as $text) {
            $this->assertStringContainsString('Contrôle des fiches produits', $this->say($text)->json('reply.body'), $text);
        }
    }

    public function test_the_audit_suggests_ai_completion_only_when_the_ai_is_on(): void
    {
        $off = $this->say('mettre à jour les fiches produits');
        $this->assertStringContainsString('activez la compréhension avancée', $off->json('reply.body'));
        $this->assertNotContains('complète les descriptions et catégories des fiches produits', array_column($off->json('reply.suggestions'), 'text'));

        $this->enableAi();
        $on = $this->say('mettre à jour les fiches produits');
        $this->assertContains('complète les descriptions et catégories des fiches produits', array_column($on->json('reply.suggestions'), 'text'));
        $this->assertContains('révise les prix des fiches produits avec une marge de 25 %', array_column($on->json('reply.suggestions'), 'text'));
    }

    public function test_a_complete_catalog_says_so(): void
    {
        $this->bare->update(['p_description' => 'Disque abrasif', 'category_id' => $this->tools->id, 'p_salePrice' => 70]);
        ProductImage::create(['product_id' => $this->bare->id, 'url' => '/storage/products/c.jpg', 'title' => 'c', 'isPrimary' => true]);
        $this->cheap->update(['p_salePrice' => 120]);

        $this->assertStringContainsString('Toutes les fiches sont complètes', $this->say('contrôle les fiches produits')->json('reply.body'));
    }

    // ── Descriptions et catégories par IA ────────────────────────────

    public function test_ai_proposals_are_shown_and_applied_only_after_a_click_and_only_where_missing(): void
    {
        $this->modelCompletes([
            ['id' => $this->bare->id, 'description' => 'Disque abrasif de 115 mm pour tronçonner le métal.', 'category_id' => $this->tools->id],
            ['id' => $this->complete->id, 'description' => 'Une description qui ne doit jamais remplacer celle de la fiche.', 'category_id' => $this->tools->id],
            ['id' => 999999, 'description' => 'Produit qui ne fait pas partie du lot demandé.', 'category_id' => null],
        ]);
        $before = $this->snapshot();

        $r = $this->say('complète les descriptions et catégories des fiches produits');

        $event = AgentEvent::where('type', 'catalogue_completion')->firstOrFail();
        $this->assertStringContainsString('Propositions pour 1 fiche(s)', $r->json('reply.body'));
        $this->assertStringContainsString('catégorie Outillage électroportatif', $r->json('reply.body'));
        $this->assertSame("applique les propositions du lot #{$event->id}", $r->json('reply.suggestions.0.text'));
        $this->assertSame($before, $this->snapshot());                       // proposition seulement

        // Entre la proposition et le clic, quelqu'un saisit la description à la main : elle est respectée.
        $this->bare->update(['p_description' => 'Description saisie à la main']);

        $done = $this->say("applique les propositions du lot #{$event->id}");

        $this->assertStringContainsString('0 description(s) et 1 catégorie(s)', $done->json('reply.body'));
        $this->assertSame('Description saisie à la main', $this->bare->fresh()->p_description);
        $this->assertSame($this->tools->id, $this->bare->fresh()->category_id);
        $this->assertSame('Perceuse sans fil 18 volts', $this->complete->fresh()->p_description);
        $this->assertSame('done', $event->fresh()->status);
        $this->assertSame('catalog_completion_applied', AgentAction::where('event_id', $event->id)->firstOrFail()->action);

        $this->assertStringContainsString('déjà été traité', $this->say("applique les propositions du lot #{$event->id}")->json('reply.body'));
    }

    public function test_a_category_to_create_is_created_once_only_at_apply_and_reused_for_the_same_name(): void
    {
        $disc = Product::factory()->create(['p_title' => 'Disque à lamelles 125 mm', 'p_sku' => 'DISQ125', 'p_description' => 'Disque à lamelles 125 mm', 'category_id' => $this->uncategorized->id]);
        ProductImage::create(['product_id' => $disc->id, 'url' => '/storage/products/d.jpg', 'title' => 'd', 'isPrimary' => true]);
        $this->modelCompletes([
            ['id' => $this->bare->id, 'description' => 'Disque à tronçonner de 115 mm pour la découpe du métal.', 'new_category' => 'abrasifs et disques'],
            ['id' => $disc->id, 'description' => null, 'new_category' => 'Abrasifs et Disques'],   // même nom, autre casse : la même catégorie
        ]);

        $r = $this->say('complète les descriptions et catégories des fiches produits');

        $event = AgentEvent::where('type', 'catalogue_completion')->firstOrFail();
        $this->assertStringContainsString('Catégories qui seraient créées : Abrasifs et disques', $r->json('reply.body'));
        $this->assertStringContainsString('catégorie Abrasifs et disques (à créer)', $r->json('reply.body'));
        $this->assertSame(0, Category::where('ctg_title', 'like', 'Abrasifs%')->count());   // rien avant le clic
        $this->assertSame($this->uncategorized->id, $this->bare->fresh()->category_id);

        $done = $this->say("applique les propositions du lot #{$event->id}");

        $this->assertStringContainsString('1 catégorie(s) créée(s)', $done->json('reply.body'));
        $this->assertSame(1, Category::where('ctg_title', 'Abrasifs et disques')->count());
        $newId = Category::where('ctg_title', 'Abrasifs et disques')->value('id');
        $this->assertSame($newId, $this->bare->fresh()->category_id);
        $this->assertSame($newId, $disc->fresh()->category_id);
        $this->assertStringContainsString('Disque à tronçonner de 115 mm', $this->bare->fresh()->p_description);
    }

    public function test_one_click_applies_a_lot_and_prepares_the_next_one_which_still_needs_validation(): void
    {
        // 11 fiches à catégoriser : un lot de 10, puis un second lot d'une fiche.
        $others = collect(range(1, 10))->map(fn (int $i) => Product::factory()->create([
            'p_title' => "Article {$i}", 'p_sku' => "ART{$i}", 'p_description' => "Article {$i} de test", 'category_id' => $this->uncategorized->id,
        ]));
        $this->enableAi();
        $reply = fn (array $rows) => Http::response(['content' => [['type' => 'tool_use', 'name' => 'complete_products', 'input' => ['products' => $rows]]]]);
        $lot1 = array_merge([['id' => $this->bare->id, 'category_id' => $this->tools->id]], $others->take(9)->map(fn ($p) => ['id' => $p->id, 'category_id' => $this->tools->id])->all());
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->pushResponse($reply($lot1))
            ->pushResponse($reply([['id' => $others->last()->id, 'new_category' => 'Divers de chantier']]))]);

        $first = $this->say('complète les descriptions et catégories des fiches produits');
        $firstEvent = AgentEvent::where('type', 'catalogue_completion')->firstOrFail();
        $texts = array_column($first->json('reply.suggestions'), 'text');
        $this->assertContains("applique les propositions du lot #{$firstEvent->id} et prépare le lot suivant", $texts);

        $both = $this->say("applique les propositions du lot #{$firstEvent->id} et prépare le lot suivant");

        $this->assertSame('done', $firstEvent->fresh()->status);
        $this->assertSame($this->tools->id, $this->bare->fresh()->category_id);
        $this->assertStringContainsString("Lot #{$firstEvent->id} appliqué", $both->json('reply.body'));
        $this->assertStringContainsString('— Lot suivant —', $both->json('reply.body'));
        $secondEvent = AgentEvent::where('type', 'catalogue_completion')->where('id', '>', $firstEvent->id)->firstOrFail();
        $this->assertSame('routed', $secondEvent->status);                      // le suivant attend toujours votre clic
        $this->assertSame($this->uncategorized->id, $others->last()->fresh()->category_id);
        $this->assertSame(0, Category::where('ctg_title', 'Divers de chantier')->count());
        $this->assertContains("applique les propositions du lot #{$secondEvent->id}", array_column($both->json('reply.suggestions'), 'text'));
        $this->assertNotContains("applique les propositions du lot #{$secondEvent->id} et prépare le lot suivant", array_column($both->json('reply.suggestions'), 'text')); // dernier lot
    }

    public function test_a_plain_apply_offers_a_button_for_the_next_lot_only_while_fiches_remain(): void
    {
        // Seule la catégorie manque à cette fiche : une fois le lot appliqué, il ne reste plus rien à compléter.
        $this->bare->update(['p_description' => 'Disque abrasif de 115 mm pour tronçonner le métal']);
        $this->modelCompletes([['id' => $this->bare->id, 'category_id' => $this->tools->id]]);
        $this->say('complète les descriptions et catégories des fiches produits');
        $event = AgentEvent::where('type', 'catalogue_completion')->firstOrFail();

        $done = $this->say("applique les propositions du lot #{$event->id}");

        // Plus aucune fiche à compléter : pas de bouton « lot suivant ».
        $this->assertSame([], $done->json('reply.suggestions'));
    }

    public function test_a_proposal_can_be_ignored_and_nothing_changes(): void
    {
        $this->modelCompletes([['id' => $this->bare->id, 'description' => 'Disque abrasif de 115 mm pour tronçonner le métal.', 'category_id' => $this->tools->id]]);
        $this->say('complète les descriptions et catégories des fiches produits');
        $event = AgentEvent::where('type', 'catalogue_completion')->firstOrFail();
        $before = $this->snapshot();

        $this->say("ignore le lot #{$event->id}");

        $this->assertSame('rejected', $event->fresh()->status);
        $this->assertSame($before, $this->snapshot());
        $this->assertStringContainsString('déjà été traité', $this->say("applique les propositions du lot #{$event->id}")->json('reply.body'));
    }

    public function test_without_the_ai_or_on_failure_completion_says_why(): void
    {
        $this->assertStringContainsString('désactivée', $this->say('complète les descriptions des fiches produits')->json('reply.body'));

        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Your credit balance is too low']], 400)]);
        $this->assertStringContainsString('crédit', $this->say('complète les descriptions des fiches produits')->json('reply.body'));
        $this->assertSame(0, AgentEvent::where('type', 'catalogue_completion')->count());
    }

    public function test_the_models_output_is_cleaned(): void
    {
        $enricher = app(CatalogEnricher::class);
        $categories = [$this->tools->id => 'Outillage électroportatif'];

        $out = $enricher->clean(['products' => [
            ['id' => $this->bare->id, 'description' => '  Disque   abrasif de 115 mm pour tronçonner. ', 'category_id' => 424242],   // catégorie inexistante
            ['id' => $this->bare->id, 'description' => 'Doublon ignoré, déjà traité plus haut ici.'],
            ['id' => $this->cheap->id, 'description' => 'Trop court'],                                                          // rien d'exploitable
            ['id' => $this->complete->id, 'description' => 'Ne remplace pas une description existante.', 'category_id' => $this->tools->id],
            'pas un tableau',
        ]], [$this->bare, $this->cheap, $this->complete], $categories);

        $this->assertCount(1, $out);
        $this->assertSame('Disque abrasif de 115 mm pour tronçonner.', $out[0]['description']);
        $this->assertNull($out[0]['category_id']);
        $this->assertNull($out[0]['new_category']);

        // Nom de catégorie proposé : refusé s'il est invalide ou « par défaut », rapproché d'une catégorie qui existe déjà.
        $names = fn (string $name) => $enricher->clean(['products' => [['id' => $this->bare->id, 'description' => null, 'new_category' => $name]]], [$this->bare], $categories);
        $this->assertSame([], $names('x'));
        $this->assertSame([], $names('<script>alert(1)</script>'));
        $this->assertSame([], $names('Non catégorisé'));
        $this->assertSame([], $names(str_repeat('a', 41)));
        $this->assertSame($this->tools->id, $names('OUTILLAGE ELECTROPORTATIF')[0]['category_id']);   // existe déjà : pas une nouvelle
        $this->assertSame('Quincaillerie générale', $names('quincaillerie générale')[0]['new_category']);
    }

    // ── Prix ─────────────────────────────────────────────────────────

    public function test_prices_ask_for_a_margin_then_propose_then_apply_with_the_screens_own_calculation(): void
    {
        $ask = $this->say('révise les prix des fiches produits');
        $this->assertStringContainsString('dites-moi la marge', $ask->json('reply.body'));
        $this->assertCount(3, $ask->json('reply.suggestions'));
        $this->assertSame(0, AgentEvent::where('type', 'catalogue_prix')->count());

        $r = $this->say('révise les prix des fiches produits avec une marge de 25 %');

        $event = AgentEvent::where('type', 'catalogue_prix')->firstOrFail();
        $this->assertStringContainsString('2 fiche(s) sans prix ou vendues sous leur prix d\'achat', $r->json('reply.body'));
        $this->assertStringContainsString('Disque à tronçonner 115 mm', $r->json('reply.body'));
        $this->assertSame("applique la révision des prix du lot #{$event->id}", $r->json('reply.suggestions.0.text'));
        $this->assertEquals(0.0, (float) $this->bare->fresh()->p_salePrice);   // rien avant le clic
        $this->assertEquals(40.0, (float) $this->cheap->fresh()->p_salePrice);
        $this->assertEquals(100.0, (float) $this->complete->fresh()->p_salePrice); // fiche saine : jamais touchée

        $rule = ['mode' => 'percent', 'value' => 25.0, 'basis' => 'purchase', 'rounding' => BulkSalePriceUpdater::DEFAULT_ROUNDING];
        $expectedBare = round(app(BulkSalePriceUpdater::class)->newPriceFor($this->bare->fresh(), $rule), 2);
        $expectedCheap = round(app(BulkSalePriceUpdater::class)->newPriceFor($this->cheap->fresh(), $rule), 2);

        $this->say("applique la révision des prix du lot #{$event->id}");

        $this->assertEquals($expectedBare, (float) $this->bare->fresh()->p_salePrice);
        $this->assertEquals($expectedCheap, (float) $this->cheap->fresh()->p_salePrice);
        $this->assertEquals(100.0, (float) $this->complete->fresh()->p_salePrice);
        $this->assertSame('done', $event->fresh()->status);
        $this->assertSame('catalog_prices_applied', AgentAction::where('event_id', $event->id)->firstOrFail()->action);
    }

    public function test_a_price_lot_only_touches_the_products_that_were_quoted(): void
    {
        $this->say('révise les prix des fiches produits avec une marge de 25 %');
        $event = AgentEvent::where('type', 'catalogue_prix')->firstOrFail();

        // Un produit à corriger apparaît entre le chiffrage et le clic : il n'est pas dans le lot montré, il n'est pas touché.
        $intruder = Product::factory()->create(['p_title' => 'Intrus', 'p_purchasePrice' => 30, 'p_salePrice' => 0]);
        WarehouseHasStock::factory()->create(['warehouse_id' => Warehouse::first()->id, 'product_id' => $intruder->id, 'stockLevel' => 3]);

        $this->say("applique la révision des prix du lot #{$event->id}");

        $this->assertEquals(0.0, (float) $intruder->fresh()->p_salePrice);
        $this->assertGreaterThan(0.0, (float) $this->bare->fresh()->p_salePrice);
    }

    public function test_a_price_lot_is_refused_when_a_quoted_product_dropped_out_of_the_perimeter(): void
    {
        $this->say('révise les prix des fiches produits avec une marge de 25 %');
        $event = AgentEvent::where('type', 'catalogue_prix')->firstOrFail();

        // Un produit du lot n'a plus de stock : le chiffrage n'est plus celui qui a été montré.
        WarehouseHasStock::where('product_id', $this->cheap->id)->update(['stockLevel' => 0]);
        $before = $this->snapshot();

        $r = $this->say("applique la révision des prix du lot #{$event->id}");

        $this->assertStringContainsString("n'a pas été appliqué", $r->json('reply.body'));
        $this->assertSame($before, $this->snapshot());
        $this->assertSame('routed', $event->fresh()->status);
    }

    public function test_an_unusable_margin_is_refused(): void
    {
        $this->assertStringContainsString("n'est pas utilisable", $this->say('révise les prix des fiches produits avec une marge de 500 %')->json('reply.body'));
        $this->assertStringContainsString("n'est pas utilisable", $this->say('révise les prix avec une marge de 120 % sur le prix de vente')->json('reply.body'));
        $this->assertSame(0, AgentEvent::where('type', 'catalogue_prix')->count());
    }

    // ── Photos ───────────────────────────────────────────────────────

    public function test_missing_photos_are_listed_and_the_deposit_flow_is_pointed_to(): void
    {
        $this->bare->update(['p_sku' => 'JD0001']);

        $body = $this->say('quels produits sont sans photo ?')->json('reply.body');

        $this->assertStringContainsString('1 produit(s) sans photo', $body);
        $this->assertStringContainsString('Disque à tronçonner 115 mm (JD0001)', $body);
        $this->assertStringNotContainsString('Perceuse 18V', $body);
        $this->assertStringContainsString('Déposez une photo ici', $body);
        $this->assertStringContainsString('1 produit(s) Jadever', $body);
    }

    // ── Phrase libre par l'IA ────────────────────────────────────────

    public function test_a_free_phrase_can_be_routed_to_the_audit_by_the_ai(): void
    {
        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'route_request', 'input' => ['intent' => 'fiches_controle']]]])]);

        $r = $this->say("fais le ménage dans ce qu'on vend, il y a des trous partout");

        $this->assertStringContainsString('Contrôle des fiches produits', $r->json('reply.body'));
        $this->assertTrue($r->json('reply.ai'));
    }
}
