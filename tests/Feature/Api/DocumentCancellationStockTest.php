<?php

namespace Tests\Feature\Api;

use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\Product;
use App\Models\StockMouvement;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Cancelling a document has to give the stock back. Every "Annuler" button
 * goes through PATCH /documents/{id}, which used to write the status and
 * nothing else — a cancelled BR kept crediting its received quantity.
 */
class DocumentCancellationStockTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin     = User::factory()->admin()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product   = Product::factory()->create();

        WarehouseHasStock::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id'   => $this->product->id,
            'stockLevel'   => 10,
        ]);
    }

    private function draftDocument(string $type, string $partnerRole, float $quantity = 4): DocumentHeader
    {
        $document = DocumentHeader::factory()->create([
            'document_incrementor_id' => DocumentIncrementor::factory()->create()->id,
            'document_type'           => $type,
            'thirdPartner_id'         => ThirdPartner::factory()->create(['tp_Role' => $partnerRole])->id,
            'warehouse_id'            => $this->warehouse->id,
            'status'                  => 'draft',
            'user_id'                 => $this->admin->id,
        ]);

        DocumentLigne::factory()->create([
            'document_header_id' => $document->id,
            'product_id'         => $this->product->id,
            'quantity'           => $quantity,
            'unit_price'         => 100,
        ]);

        return $document;
    }

    private function stockLevel(): float
    {
        return (float) WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('stockLevel');
    }

    public function test_cancelling_a_confirmed_receipt_note_gives_the_received_quantity_back(): void
    {
        $br = $this->draftDocument('ReceiptNotePurchase', 'supplier');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/achats/documents/{$br->id}/confirmer-br")
            ->assertOk();

        $this->assertSame(14.0, $this->stockLevel(), 'reception should add to stock');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/documents/{$br->id}", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(10.0, $this->stockLevel(), 'cancellation should hand the quantity back');
        $this->assertSame(0, StockMouvement::where('document_header_id', $br->id)
            ->whereIn('status', ['pending', 'applied'])
            ->where('reason', '!=', 'cancellation')
            ->count());
    }

    public function test_cancelling_a_confirmed_delivery_note_restores_the_exit(): void
    {
        $bl = $this->draftDocument('DeliveryNote', 'customer');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/ventes/documents/{$bl->id}/confirmer-bl")
            ->assertOk();

        $this->assertSame(6.0, $this->stockLevel(), 'delivery should deduct from stock');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/documents/{$bl->id}", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(10.0, $this->stockLevel(), 'cancellation should put the quantity back');
    }

    public function test_cancelling_twice_does_not_double_reverse(): void
    {
        $br = $this->draftDocument('ReceiptNotePurchase', 'supplier');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/achats/documents/{$br->id}/confirmer-br")
            ->assertOk();

        foreach (range(1, 2) as $_) {
            $this->actingAs($this->admin, 'sanctum')
                ->patchJson("/api/documents/{$br->id}", ['status' => 'cancelled'])
                ->assertOk();
        }

        $this->assertSame(10.0, $this->stockLevel());
    }

    public function test_reversing_an_already_reversed_document_is_a_no_op(): void
    {
        // What documents:repair-cancelled-stock does: call the service straight
        // on a document that may already carry its compensating entries. Those
        // are 'applied' too, so a naive second pass would bounce the stock back.
        $br = $this->draftDocument('ReceiptNotePurchase', 'supplier');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/achats/documents/{$br->id}/confirmer-br")
            ->assertOk();

        $service = app(\App\Services\StockMouvementService::class);

        $this->actingAs($this->admin);
        $service->cancelDocumentMovements($br);
        $this->assertSame(10.0, $this->stockLevel());

        $service->cancelDocumentMovements($br->fresh());
        $this->assertSame(10.0, $this->stockLevel(), 'a second reversal must change nothing');
    }

    public function test_a_status_change_that_is_not_a_cancellation_leaves_stock_alone(): void
    {
        $br = $this->draftDocument('ReceiptNotePurchase', 'supplier');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/achats/documents/{$br->id}/confirmer-br")
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/documents/{$br->id}", ['document_title' => 'Titre modifié'])
            ->assertOk();

        $this->assertSame(14.0, $this->stockLevel());
    }
}
