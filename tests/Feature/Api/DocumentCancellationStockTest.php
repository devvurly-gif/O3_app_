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
use App\Services\StockMouvementService;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Undoing a document: a draft is cancelled, a committed one is returned.
 *
 * PATCH /documents/{id} used to accept 'cancelled' for anything and write the
 * status alone — a confirmed BR kept crediting its received quantity to the
 * warehouse. It now refuses committed documents (they go through a return
 * document, which carries its own reference and stock movements) and still
 * reverses whatever movements a cancellable document was holding.
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

    // ── Committed documents are returned, not cancelled ──────────────

    public function test_a_confirmed_receipt_note_cannot_be_cancelled(): void
    {
        $br = $this->draftDocument('ReceiptNotePurchase', 'supplier');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/achats/documents/{$br->id}/confirmer-br")
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/documents/{$br->id}", ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Retour Fournisseur'));

        $this->assertSame('confirmed', $br->fresh()->status, 'the status must not have moved');
        $this->assertSame(14.0, $this->stockLevel(), 'a refused cancellation must not touch stock');
    }

    public function test_a_confirmed_delivery_note_cannot_be_cancelled(): void
    {
        $bl = $this->draftDocument('DeliveryNote', 'customer');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/ventes/documents/{$bl->id}/confirmer-bl")
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/documents/{$bl->id}", ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Retour Client'));

        $this->assertSame(6.0, $this->stockLevel());
    }

    public function test_a_supplier_return_takes_the_received_quantity_back_out(): void
    {
        $br = $this->draftDocument('ReceiptNotePurchase', 'supplier');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/achats/documents/{$br->id}/confirmer-br")
            ->assertOk();

        $this->assertSame(14.0, $this->stockLevel());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/achats/documents/{$br->id}/retour-fournisseur")
            ->assertCreated()
            ->assertJsonPath('data.document_type', 'ReturnPurchase');

        $this->assertSame(10.0, $this->stockLevel(), 'the goods went back to the supplier');
    }

    public function test_a_customer_return_puts_the_delivered_quantity_back(): void
    {
        $bl = $this->draftDocument('DeliveryNote', 'customer');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/ventes/documents/{$bl->id}/confirmer-bl")
            ->assertOk();

        $this->assertSame(6.0, $this->stockLevel());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/ventes/documents/{$bl->id}/retour-client")
            ->assertCreated()
            ->assertJsonPath('data.document_type', 'ReturnSale');

        $this->assertSame(10.0, $this->stockLevel(), 'the goods came back from the customer');
    }

    // ── Drafts stay cancellable, and give back what they held ────────

    public function test_a_draft_can_still_be_cancelled(): void
    {
        $br = $this->draftDocument('ReceiptNotePurchase', 'supplier');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/documents/{$br->id}", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame('cancelled', $br->fresh()->status);
        $this->assertSame(10.0, $this->stockLevel(), 'a draft never moved stock');
    }

    public function test_cancelling_a_draft_clears_the_movements_it_was_holding(): void
    {
        $br = $this->draftDocument('ReceiptNotePurchase', 'supplier');

        // A BR generated from a purchase order carries pending movements
        // before anyone confirms it.
        app(StockMouvementService::class)->processDocument($br->load('lignes'), pending: true);
        $this->assertSame(1, StockMouvement::where('document_header_id', $br->id)->where('status', 'pending')->count());

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/documents/{$br->id}", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(0, StockMouvement::where('document_header_id', $br->id)->where('status', 'pending')->count());
        $this->assertSame(10.0, $this->stockLevel());
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

    // ── The repair command's own guarantee ───────────────────────────

    public function test_reversing_an_already_reversed_document_is_a_no_op(): void
    {
        // documents:repair-cancelled-stock calls the service straight on
        // documents that may already carry compensating entries. Those are
        // 'applied' too, so a naive second pass would bounce the stock back.
        $br = $this->draftDocument('ReceiptNotePurchase', 'supplier');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/achats/documents/{$br->id}/confirmer-br")
            ->assertOk();

        $service = app(StockMouvementService::class);

        $this->actingAs($this->admin);
        $service->cancelDocumentMovements($br);
        $this->assertSame(10.0, $this->stockLevel());

        $service->cancelDocumentMovements($br->fresh());
        $this->assertSame(10.0, $this->stockLevel(), 'a second reversal must change nothing');
    }
}
