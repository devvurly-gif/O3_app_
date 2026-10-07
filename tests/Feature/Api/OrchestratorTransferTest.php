<?php

namespace Tests\Feature\Api;

use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\Product;
use App\Models\Setting;
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
 * « Propose des transferts entre entrepôts » : seulement quand un entrepôt a du surplus, brouillons créés après le clic,
 * aucun stock déplacé.
 */
class OrchestratorTransferTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private Warehouse $main;
    private Warehouse $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::swap(new Factory());
        Http::fake();
        Setting::set('stock', 'seuil_alerte_stock', '5');
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->main = Warehouse::factory()->create(['wh_title' => 'Dépôt central', 'wh_status' => true]);
        $this->shop = Warehouse::factory()->create(['wh_title' => 'Magasin', 'wh_status' => true]);
        DocumentIncrementor::factory()->create(['di_model' => 'StockTransfer', 'di_title' => 'Bon de Transfert', 'template' => 'TRF-{YYYY}-{NNNN}', 'nextTrick' => 1]);
    }

    private function say(string $text): array
    {
        $r = $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
        Http::assertNothingSent();

        return $r;
    }

    private function stock(string $title, float $central, float $shop): Product
    {
        $p = Product::factory()->create(['p_title' => $title, 'p_status' => true, 'p_purchasePrice' => 50]);
        foreach ([[$this->main, $central], [$this->shop, $shop]] as [$w, $level]) {
            DB::table('warehouse_has_stock')->insert(['product_id' => $p->id, 'warehouse_id' => $w->id, 'stockLevel' => $level, 'wh_average' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $p;
    }

    private function level(Product $p, Warehouse $w): float
    {
        return (float) DB::table('warehouse_has_stock')->where('product_id', $p->id)->where('warehouse_id', $w->id)->value('stockLevel');
    }

    public function test_it_proposes_moving_the_surplus_to_the_warehouse_in_shortage(): void
    {
        $a = $this->stock('Vis A', 40, 2);
        $b = $this->stock('Vis B', 12, 0);          // surplus du donneur : 2 seulement
        $this->stock('Joint C', 3, 1);              // bas partout : pas un transfert
        $this->stock('Écrou D', 30, 30);

        $r = $this->say('propose des transferts entre entrepôts');

        $this->assertStringContainsString('1 bon(s) de transfert à préparer', $r['body']);
        $this->assertStringContainsString('Dépôt central → Magasin — 2 produit(s)', $r['body']);
        $this->assertStringContainsString('Vis A : 8 à déplacer', $r['body']);               // 10 - 2
        $this->assertStringContainsString('Vis B : 2 à déplacer', $r['body']);               // le donneur garde 10
        $this->assertStringContainsString('1 autre(s) produit(s) sont bas partout', $r['body']);
        $this->assertStringNotContainsString('Écrou D', $r['body']);
        $this->assertSame(0, DocumentHeader::where('document_type', 'StockTransfer')->count());
        $this->assertSame([40.0, 2.0], [$this->level($a, $this->main), $this->level($a, $this->shop)]);
        $this->assertNotNull($b->id);
    }

    public function test_the_click_creates_a_draft_transfer_that_moves_no_stock(): void
    {
        $a = $this->stock('Vis A', 40, 2);

        $r = $this->say('propose des transferts entre entrepôts');
        $done = $this->say($r['suggestions'][0]['text']);

        $this->assertStringContainsString('1 bon(s) de transfert brouillon créé(s)', $done['body']);
        $doc = DocumentHeader::where('document_type', 'StockTransfer')->firstOrFail();
        $this->assertSame('draft', $doc->status);
        $this->assertSame($this->main->id, $doc->warehouse_id);
        $this->assertSame($this->shop->id, $doc->warehouse_dest_id);
        $this->assertSame($this->admin->id, $doc->user_id);
        $this->assertSame(8.0, (float) $doc->lignes->first()->quantity);
        $this->assertSame('transfer_drafts_created', AgentAction::first()->action);
        $this->assertSame([40.0, 2.0], [$this->level($a, $this->main), $this->level($a, $this->shop)]);
        $this->assertSame(0, DB::table('stock_mouvements')->count());

        $again = $this->say('propose des transferts entre entrepôts');                         // déjà sur un bon ouvert
        $this->assertStringContainsString('Aucun transfert utile', $again['body']);
    }

    public function test_ignoring_changes_nothing_and_one_warehouse_means_no_proposal(): void
    {
        $this->stock('Vis A', 40, 2);
        $r = $this->say('prépare un transfert entre les entrepôts');
        $this->say($r['suggestions'][1]['text']);
        $this->assertSame(0, DocumentHeader::where('document_type', 'StockTransfer')->count());
        $this->assertSame(0, AgentAction::count());
        $this->assertNotSame(AgentEvent::STATUS_DONE, AgentEvent::where('type', 'transfert_entrepots')->first()->status);

        $this->shop->update(['wh_status' => false]);
        $this->assertStringContainsString('un seul entrepôt actif existe', $this->say('propose des transferts entre entrepôts')['body']);
    }
}
