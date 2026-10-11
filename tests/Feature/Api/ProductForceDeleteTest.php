<?php

namespace Tests\Feature\Api;

use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Product;
use App\Models\StockMouvement;
use App\Models\User;
use App\Models\Warehouse;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Suppression définitive d'un produit de la corbeille (« Produits supprimés ») : admin seulement, et seulement
 * un produit déjà supprimé qui n'a plus d'historique (stock, transferts, documents).
 */
class ProductForceDeleteTest extends TestCase
{
    use RefreshTenantDatabase;

    private function trashedProduct(): Product
    {
        $p = Product::factory()->create(['p_sku' => 'TRASH-001']);
        $p->delete();

        return $p;
    }

    public function test_an_admin_permanently_deletes_a_trashed_product_and_frees_its_sku(): void
    {
        $p = $this->trashedProduct();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/products/{$p->id}/definitif")->assertNoContent();

        $this->assertNull(Product::withTrashed()->find($p->id));
        $this->assertDatabaseHas('activity_log', ['description' => 'Produit TRASH-001 supprimé définitivement']);
        Product::factory()->create(['p_sku' => 'TRASH-001']);                 // le SKU est libre à nouveau
    }

    public function test_only_a_trashed_product_can_be_permanently_deleted(): void
    {
        $live = Product::factory()->create();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum')->deleteJson("/api/products/{$live->id}/definitif")
            ->assertStatus(422)->assertJsonFragment(['message' => 'Seul un produit déjà supprimé peut être supprimé définitivement.']);
        $this->assertNotNull(Product::find($live->id));
    }

    public function test_a_product_with_stock_history_or_in_documents_is_kept(): void
    {
        $admin = User::factory()->admin()->create();
        $warehouse = Warehouse::factory()->create();

        $withMove = $this->trashedProduct();
        StockMouvement::create([
            'product_id' => $withMove->id, 'warehouse_id' => $warehouse->id, 'document_reference' => 'X', 'document_type' => 'StockEntry',
            'direction' => 'in', 'reason' => 'stock_entry', 'quantity' => 1, 'unit_cost' => 0, 'stock_before' => 0, 'stock_after' => 1,
            'user_id' => $admin->id, 'status' => 'applied',
        ]);
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/products/{$withMove->id}/definitif")
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, '1 mouvement(s) de stock'));

        $inDoc = Product::factory()->create(['p_sku' => 'TRASH-002']);
        DocumentLigne::create(['document_header_id' => DocumentHeader::factory()->create()->id, 'product_id' => $inDoc->id, 'designation' => 'x', 'quantity' => 1, 'unit_price' => 1]);
        $inDoc->delete();
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/products/{$inDoc->id}/definitif")
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, '1 ligne(s) de document'));

        $this->assertNotNull(Product::withTrashed()->find($withMove->id));
        $this->assertNotNull(Product::withTrashed()->find($inDoc->id));
    }

    public function test_a_manager_cannot_permanently_delete(): void
    {
        $p = $this->trashedProduct();

        $this->actingAs(User::factory()->manager()->create(), 'sanctum')->deleteJson("/api/products/{$p->id}/definitif")->assertForbidden();
        $this->assertNotNull(Product::withTrashed()->find($p->id));
    }
}
