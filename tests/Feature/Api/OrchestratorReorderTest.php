<?php

namespace Tests\Feature\Api;

use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Réapprovisionne le stock faible » : une proposition par fournisseur, jamais de fournisseur deviné, brouillons de bons
 * de commande créés seulement après le clic, rien d'envoyé, aucun stock modifié.
 */
class OrchestratorReorderTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::swap(new Factory());
        Http::fake();
        Setting::set('stock', 'seuil_alerte_stock', '5');
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->warehouse = Warehouse::factory()->create();
        DocumentIncrementor::factory()->create(['di_model' => 'PurchaseOrder', 'di_title' => 'Bon de Commande Fournisseur', 'template' => 'BCF-{YYYY}-{NNNN}', 'nextTrick' => 1]);
    }

    private function say(string $text): array
    {
        $r = $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
        Http::assertNothingSent();

        return $r;
    }

    private function product(string $title, float $stock, array $attrs = []): Product
    {
        $p = Product::factory()->create(array_merge(['p_title' => $title, 'p_status' => true, 'p_taxRate' => 20, 'p_purchasePrice' => 120], $attrs));
        DB::table('warehouse_has_stock')->insert(['product_id' => $p->id, 'warehouse_id' => $this->warehouse->id, 'stockLevel' => $stock, 'wh_average' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $p;
    }

    private function link(Product $p, ThirdPartner $s, ?float $price = null): void
    {
        DB::table('product_suppliers')->insert(['product_id' => $p->id, 'third_partner_id' => $s->id, 'purchase_price' => $price, 'priority' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_it_groups_low_stock_by_supplier_and_creates_nothing_before_the_click(): void
    {
        $atlas = ThirdPartner::factory()->create(['tp_title' => 'Atlas Import', 'tp_Role' => 'supplier']);
        $nord = ThirdPartner::factory()->create(['tp_title' => 'Nord Pièces', 'tp_Role' => 'supplier']);
        $a = $this->product('Vis A', 2);
        $b = $this->product('Vis B', 0);
        $c = $this->product('Joint C', 5);
        $this->product('Stock correct', 30);
        $orphan = $this->product('Sans fournisseur', 1);
        $this->link($a, $atlas);
        $this->link($b, $atlas, 60);
        $this->link($c, $nord);

        $r = $this->say('réapprovisionne le stock faible');

        $this->assertStringContainsString('2 bon(s) de commande à préparer', $r['body']);
        $this->assertStringContainsString('Atlas Import — 2 produit(s)', $r['body']);
        $this->assertStringContainsString('Nord Pièces — 1 produit(s)', $r['body']);
        $this->assertStringContainsString('Vis A : stock 2, à commander 8', $r['body']);       // retour à 10 pièces
        $this->assertStringContainsString('Vis B : stock 0, à commander 10', $r['body']);
        $this->assertStringContainsString('Sans fournisseur', $r['body']);                       // listé à part
        $this->assertStringNotContainsString('Stock correct', $r['body']);
        $this->assertSame('Créer les brouillons de commande', $r['suggestions'][0]['label']);
        $this->assertSame(0, DocumentHeader::where('document_type', 'PurchaseOrder')->count());
        $this->assertSame(AgentEvent::STATUS_ROUTED, AgentEvent::where('type', 'reappro_commande')->first()->status);
        $this->assertSame(5.0, (float) DB::table('warehouse_has_stock')->where('product_id', $c->id)->value('stockLevel'));
        $this->assertNotNull($orphan->id);
    }

    public function test_the_click_creates_one_draft_per_supplier_with_ht_prices_and_logs_it(): void
    {
        $atlas = ThirdPartner::factory()->create(['tp_title' => 'Atlas Import', 'tp_Role' => 'supplier']);
        $a = $this->product('Vis A', 2, ['p_purchasePrice' => 120]);
        $b = $this->product('Vis B', 0);
        $this->link($a, $atlas);
        $this->link($b, $atlas, 60);                                                            // prix fournisseur TTC

        $r = $this->say('réapprovisionne le stock faible');
        $done = $this->say($r['suggestions'][0]['text']);

        $this->assertStringContainsString('1 bon(s) de commande brouillon créé(s)', $done['body']);
        $po = DocumentHeader::where('document_type', 'PurchaseOrder')->firstOrFail();
        $this->assertSame('draft', $po->status);
        $this->assertSame($atlas->id, $po->thirdPartner_id);
        $this->assertSame($this->admin->id, $po->user_id);
        $this->assertCount(2, $po->lignes);
        $lineA = $po->lignes->firstWhere('product_id', $a->id);
        $this->assertSame(8.0, (float) $lineA->quantity);
        $this->assertSame(100.0, (float) $lineA->unit_price);                                   // 120 TTC / 1,20
        $this->assertSame(50.0, (float) $po->lignes->firstWhere('product_id', $b->id)->unit_price);
        $this->assertSame(AgentEvent::STATUS_DONE, AgentEvent::where('type', 'reappro_commande')->first()->status);
        $this->assertSame('reorder_drafts_created', AgentAction::first()->action);
        $this->assertSame(2.0, (float) DB::table('warehouse_has_stock')->where('product_id', $a->id)->value('stockLevel'));   // le stock ne bouge pas
    }

    public function test_products_already_on_an_open_order_are_skipped_and_ignoring_changes_nothing(): void
    {
        $atlas = ThirdPartner::factory()->create(['tp_title' => 'Atlas Import', 'tp_Role' => 'supplier']);
        $a = $this->product('Vis A', 1);
        $this->link($a, $atlas);

        $r = $this->say('prépare les bons de commande');
        $this->say($r['suggestions'][1]['text']);
        $this->assertSame(0, DocumentHeader::where('document_type', 'PurchaseOrder')->count());
        $this->assertSame(0, AgentAction::count());

        $this->say($this->say('réapprovisionne le stock faible')['suggestions'][0]['text']);
        $this->assertSame(1, DocumentHeader::where('document_type', 'PurchaseOrder')->count());

        $again = $this->say('réapprovisionne le stock faible');
        $this->assertStringContainsString('figurent déjà sur un bon de commande fournisseur ouvert', $again['body']);
    }

    public function test_the_read_questions_keep_their_meaning(): void
    {
        $this->product('Vis A', 1);

        $this->assertStringContainsString('à 5 pièce(s) ou moins', $this->say('stock faible')['body']);
        $this->assertStringContainsString('à 5 pièce(s) ou moins', $this->say('quoi réapprovisionner ?')['body']);
        $this->assertSame(0, AgentEvent::where('type', 'reappro_commande')->count());
    }
}
