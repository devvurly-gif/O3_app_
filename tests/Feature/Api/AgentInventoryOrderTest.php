<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\Product;
use App\Models\StockMouvement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Ordre « préparer un inventaire » donné à l'agent Stocks : un événement manuel
 * routé vers Stocks, une feuille Excel en brouillon, aucun stock modifié.
 */
class AgentInventoryOrderTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private Warehouse $main;
    private Warehouse $second;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->main = Warehouse::factory()->create(['wh_title' => 'Dépôt Principal', 'wh_status' => true]);
        $this->second = Warehouse::factory()->create(['wh_title' => 'Magasin', 'wh_status' => true]);

        $this->stock('NEG-1', $this->main, -3);
        $this->stock('ZERO-1', $this->main, 0);
        $this->stock('OK-1', $this->main, 12);   // normal, mais jamais de mouvement → dormant
        $this->stock('SHOP-1', $this->second, 5);
    }

    private function stock(string $sku, Warehouse $warehouse, float $level): Product
    {
        $product = Product::factory()->create(['p_sku' => $sku, 'p_title' => "Article {$sku}", 'p_status' => true]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'stockLevel' => $level]);

        return $product;
    }

    private function movement(array $attrs): StockMouvement
    {
        $m = StockMouvement::create($attrs + ["reason" => "purchase_receipt", "quantity" => 1, "stock_before" => 0, "stock_after" => 1, "status" => "applied", "user_id" => $this->admin->id]);
        if (isset($attrs["created_at"])) {
            $m->forceFill(["created_at" => $attrs["created_at"]])->saveQuietly();
        }

        return $m;
    }

    private function order(array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/ordres', $payload + ['type' => 'inventaire']);
    }

    /** @return array<int, array<int, mixed>> */
    private function sheet(string $name): array
    {
        return IOFactory::load(Storage::disk('local')->path('agents/inventaires/' . $name))->getActiveSheet()->toArray();
    }

    public function test_order_is_routed_to_stocks_and_prepares_a_sheet_without_touching_stock(): void
    {
        $before = WarehouseHasStock::pluck('stockLevel', 'id')->all();

        $r = $this->order()->assertCreated();

        $this->assertSame('stocks', $r->json('agent'));
        $this->assertSame(4, $r->json('file.rows'));
        $this->assertSame(4, $r->json('file.flagged'));   // négatif, nul, dormant ×2

        $event = AgentEvent::sole();
        $this->assertSame('inventaire_demande', $event->type);
        $this->assertSame('manual', $event->source);
        $this->assertSame(AgentEvent::STATUS_DONE, $event->status);
        $this->assertSame('stocks', $event->agent->domain);

        $action = AgentAction::sole();
        $this->assertSame('prepare_inventory_sheet', $action->action);
        $this->assertSame('approval', $action->level);

        // Rien n'a bougé dans le stock.
        $this->assertEquals($before, WarehouseHasStock::pluck('stockLevel', 'id')->all());
        $this->assertSame(0, StockMouvement::count());
    }

    public function test_sheet_lists_flagged_articles_first_with_empty_count_columns(): void
    {
        $name = $this->order()->assertCreated()->json('file.name');

        $rows = $this->sheet($name);
        $this->assertSame('Entrepôt', $rows[0][0]);
        $this->assertSame('Quantité comptée', $rows[0][8]);
        $this->assertSame('Écart', $rows[0][9]);

        // Premier article : le stock négatif (le plus urgent par l'ordre alphabétique des entrepôts/SKU parmi les à vérifier).
        $flaggedSkus = collect(array_slice($rows, 1))->filter(fn ($r) => $r[7] !== null && $r[7] !== '')->pluck(1)->all();
        $this->assertEqualsCanonicalizing(['NEG-1', 'ZERO-1', 'OK-1', 'SHOP-1'], $flaggedSkus);
        $neg = collect($rows)->firstWhere(1, 'NEG-1');
        $this->assertStringContainsString('Stock négatif', $neg[7]);
        $this->assertTrue($neg[8] === null || $neg[8] === '');      // Quantité comptée vide
        $this->assertTrue($neg[9] === null || $neg[9] === '');      // Écart vide tant que rien n'est compté
    }

    public function test_scope_attention_keeps_only_articles_to_verify_and_a_warehouse_can_be_chosen(): void
    {
        // Un article sain : mouvement récent, stock positif.
        $healthy = $this->stock('FINE-1', $this->main, 8);
        $this->movement(['product_id' => $healthy->id, 'warehouse_id' => $this->main->id, 'direction' => 'in', 'status' => 'applied', 'created_at' => now()->subDays(2)]);

        $all = $this->order(['scope' => 'all'])->assertCreated();
        $attention = $this->order(['scope' => 'attention'])->assertCreated();
        $shop = $this->order(['scope' => 'all', 'warehouse_id' => $this->second->id])->assertCreated();

        $this->assertSame(5, $all->json('file.rows'));
        $this->assertSame(4, $attention->json('file.rows'));
        $this->assertSame(1, $shop->json('file.rows'));
        $this->assertSame(['Magasin'], $shop->json('file.warehouses'));
    }

    public function test_pending_movement_is_flagged_and_shown(): void
    {
        $product = Product::where('p_sku', 'OK-1')->first();
        $this->movement(['product_id' => $product->id, 'warehouse_id' => $this->main->id, 'direction' => 'in', 'quantity' => 6, 'status' => 'pending']);

        $name = $this->order(['scope' => 'attention'])->assertCreated()->json('file.name');

        $row = collect($this->sheet($name))->firstWhere(1, 'OK-1');
        $this->assertStringContainsString('Mouvement en attente', $row[7]);
        $this->assertEquals(6, $row[5]);
    }

    public function test_an_inactive_article_with_stock_is_counted_but_not_flagged_for_being_inactive(): void
    {
        $inactive = $this->stock('IMPORTED-1', $this->main, 7);
        $inactive->update(['p_status' => false]);

        $name = $this->order()->assertCreated()->json('file.name');

        $row = collect($this->sheet($name))->firstWhere(1, 'IMPORTED-1');
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('inactif', (string) $row[7]);   // l'import crée les produits inactifs : pas un signal
        $this->assertEquals(7, $row[4]);
    }

    public function test_inactive_stocks_agent_refuses_the_order_and_leaves_it_to_sort(): void
    {
        Agent::where('domain', 'stocks')->update(['is_active' => false]);

        $this->order()->assertStatus(422);

        $this->assertSame(AgentEvent::STATUS_TO_SORT, AgentEvent::sole()->status);
        $this->assertSame(0, AgentAction::count());
    }

    public function test_the_prepared_file_can_be_downloaded(): void
    {
        $r = $this->order()->assertCreated();

        $download = $this->actingAs($this->admin, 'sanctum')->get($r->json('file.url'));
        $download->assertOk();
        $this->assertStringContainsString($r->json('file.name'), $download->headers->get('content-disposition'));
    }

    public function test_non_admin_roles_agent_tokens_and_guests_are_refused(): void
    {
        foreach (['manager', 'cashier', 'warehouse'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create(), 'sanctum')
                ->postJson('/api/agents/ordres', ['type' => 'inventaire'])->assertForbidden();
        }

        $plain = $this->admin->createToken('agent', ['achats:import'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$plain}")->postJson('/api/agents/ordres', ['type' => 'inventaire'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->postJson('/api/agents/ordres', ['type' => 'inventaire'])->assertUnauthorized();
        $this->assertSame(0, AgentEvent::count());
    }

    public function test_invalid_orders_are_rejected(): void
    {
        $this->order(['type' => 'virement'])->assertStatus(422);
        $this->order(['scope' => 'tout'])->assertStatus(422);
        $this->order(['warehouse_id' => 9999])->assertStatus(422);
        $this->assertSame(0, AgentEvent::count());
    }
}
