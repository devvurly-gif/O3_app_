<?php

namespace Tests\Feature\Api;

use App\Models\AgentAction;
use App\Models\Product;
use App\Models\StockMouvement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Écran « Inventaire » du menu Stock : feuille préparée par l'agent, comptage,
 * application des écarts par un humain, une seule fois.
 */
class StockInventoryTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $user;
    private Warehouse $warehouse;
    private Product $a;
    private Product $b;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(AgentFoundationSeeder::class);
        $this->user = User::factory()->warehouse()->create();
        $this->warehouse = Warehouse::factory()->create(['wh_title' => 'Dépôt Principal', 'wh_status' => true]);
        $this->a = $this->stock('ART-A', 10);
        $this->b = $this->stock('ART-B', 4);
    }

    private function stock(string $sku, float $level): Product
    {
        $p = Product::factory()->create(['p_sku' => $sku, 'p_title' => "Article {$sku}", 'p_status' => true]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $this->warehouse->id, 'product_id' => $p->id, 'stockLevel' => $level]);

        return $p;
    }

    private function level(Product $p): float
    {
        return (float) WarehouseHasStock::where('product_id', $p->id)->value('stockLevel');
    }

    private function as(User $u): static
    {
        return $this->actingAs($u, 'sanctum');
    }

    private function prepare(): int
    {
        return $this->as($this->user)->postJson('/api/stock/inventaires', ['scope' => 'all'])->assertCreated()->json('event_id');
    }

    private function counted(Product $p, float $counted): array
    {
        return ['warehouse_id' => $this->warehouse->id, 'product_id' => $p->id, 'counted' => $counted];
    }

    public function test_a_warehouse_user_can_prepare_and_list_a_sheet(): void
    {
        $r = $this->as($this->user)->postJson('/api/stock/inventaires', ['scope' => 'all', 'note' => 'Fin de mois'])->assertCreated();

        $this->assertSame('prepared', $r->json('status'));
        $this->assertSame(2, $r->json('rows'));
        $this->assertSame('Fin de mois', $r->json('note'));

        $list = $this->as($this->user)->getJson('/api/stock/inventaires')->assertOk();
        $this->assertCount(1, $list->json('sessions'));
        $this->assertSame($r->json('event_id'), $list->json('sessions.0.event_id'));
    }

    public function test_show_returns_the_lines_with_the_current_stock(): void
    {
        $id = $this->prepare();
        // Le stock bouge après la préparation de la feuille.
        WarehouseHasStock::where('product_id', $this->a->id)->update(['stockLevel' => 7]);

        $show = $this->as($this->user)->getJson("/api/stock/inventaires/{$id}")->assertOk();

        $line = collect($show->json('lines'))->firstWhere('sku', 'ART-A');
        $this->assertEquals(10, $line['theoretical']);
        $this->assertEquals(7, $line['current']);
        $this->assertSame($this->warehouse->id, $line['warehouse_id']);
        $this->assertCount(2, $show->json('lines'));
    }

    public function test_apply_adjusts_the_stock_to_the_counted_quantities_and_logs_it(): void
    {
        $id = $this->prepare();

        $r = $this->as($this->user)->postJson("/api/stock/inventaires/{$id}/appliquer", [
            'counts' => [$this->counted($this->a, 8), $this->counted($this->b, 4)],   // A : écart −2 ; B : déjà conforme
        ])->assertOk();

        $this->assertSame(1, $r->json('adjusted'));
        $this->assertSame(1, $r->json('unchanged'));
        $this->assertEquals(8, $this->level($this->a));
        $this->assertEquals(4, $this->level($this->b));

        $move = StockMouvement::where('reason', 'inventory_adjustment')->sole();
        $this->assertSame($this->user->id, $move->user_id);
        $this->assertStringContainsString("Inventaire #{$id}", $move->notes);

        $this->assertSame('inventory_applied', AgentAction::where('action', 'inventory_applied')->sole()->action);
        $sessions = $this->as($this->user)->getJson('/api/stock/inventaires')->json('sessions');
        $this->assertSame('applied', $sessions[0]['status']);
        $this->assertSame(1, $sessions[0]['adjusted']);
    }

    public function test_a_sheet_can_only_be_applied_once(): void
    {
        $id = $this->prepare();
        $this->as($this->user)->postJson("/api/stock/inventaires/{$id}/appliquer", ['counts' => [$this->counted($this->a, 8)]])->assertOk();

        $this->as($this->user)->postJson("/api/stock/inventaires/{$id}/appliquer", ['counts' => [$this->counted($this->a, 1)]])->assertStatus(422);

        $this->assertEquals(8, $this->level($this->a));
        $this->assertSame(1, StockMouvement::where('reason', 'inventory_adjustment')->count());
    }

    public function test_lines_that_are_not_on_the_sheet_are_never_adjusted(): void
    {
        $id = $this->prepare();
        $outsider = $this->stock('ART-C', 3);   // créé après la feuille : absent de la feuille

        $r = $this->as($this->user)->postJson("/api/stock/inventaires/{$id}/appliquer", [
            'counts' => [$this->counted($outsider, 99)],
        ])->assertOk();

        $this->assertSame(0, $r->json('adjusted'));
        $this->assertCount(1, $r->json('errors'));
        $this->assertEquals(3, $this->level($outsider));
    }

    public function test_invalid_counts_are_rejected_and_nothing_changes(): void
    {
        $id = $this->prepare();

        $this->as($this->user)->postJson("/api/stock/inventaires/{$id}/appliquer", ['counts' => [$this->counted($this->a, -5)]])->assertStatus(422);
        $this->as($this->user)->postJson("/api/stock/inventaires/{$id}/appliquer", ['counts' => []])->assertStatus(422);

        $this->assertEquals(10, $this->level($this->a));
        $this->assertSame(0, AgentAction::where('action', 'inventory_applied')->count());
    }

    public function test_the_excel_sheet_can_be_downloaded(): void
    {
        $id = $this->prepare();

        $this->as($this->user)->get("/api/stock/inventaires/{$id}/fichier")->assertOk();
    }

    public function test_only_stock_roles_and_interactive_tokens_are_allowed(): void
    {
        $this->as(User::factory()->cashier()->create())->getJson('/api/stock/inventaires')->assertForbidden();
        $this->as(User::factory()->manager()->create())->getJson('/api/stock/inventaires')->assertOk();
        $this->as(User::factory()->admin()->create())->getJson('/api/stock/inventaires')->assertOk();

        $plain = $this->user->createToken('agent', ['achats:import'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$plain}")->getJson('/api/stock/inventaires')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/stock/inventaires')->assertUnauthorized();
    }

    public function test_an_unknown_sheet_is_a_404(): void
    {
        $this->as($this->user)->getJson('/api/stock/inventaires/99999')->assertNotFound();
    }
}
