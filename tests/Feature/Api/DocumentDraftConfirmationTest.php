<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Invoicing a delivery/receipt note that is still a draft.
 *
 * Both controllers auto-confirm the note before invoicing it, and that branch
 * called `Log::info()` without the facade being imported — PHP resolved it in
 * the controller's own namespace and threw, so the whole transition answered
 * 500. No test reached the draft branch, which is how it survived.
 */
class DocumentDraftConfirmationTest extends TestCase
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
        $this->product   = Product::factory()->create(['p_taxRate' => 0]);

        WarehouseHasStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id'   => $this->product->id,
            'stockLevel'   => 100,
            'user_id'      => $this->admin->id,
        ]);
    }

    private function makeNote(string $documentType, string $partnerRole): DocumentHeader
    {
        $note = DocumentHeader::factory()->create([
            'document_type'   => $documentType,
            'status'          => 'draft',
            'thirdPartner_id' => ThirdPartner::factory()->create(['tp_Role' => $partnerRole])->id,
            'warehouse_id'    => $this->warehouse->id,
            'user_id'         => $this->admin->id,
        ]);

        DocumentLigne::factory()->create([
            'document_header_id' => $note->id,
            'product_id'         => $this->product->id,
            'quantity'           => 4,
            'unit_price'         => 250,
        ]);

        DocumentFooter::factory()->create([
            'document_header_id' => $note->id,
            'total_ht'           => 1000,
            'total_tax'          => 0,
            'total_ttc'          => 1000,
        ]);

        return $note->fresh();
    }

    // ── Ventes : BL brouillon → facture ──────────────────────────

    public function test_invoicing_a_draft_delivery_note_confirms_it_first(): void
    {
        $bl = $this->makeNote('DeliveryNote', 'customer');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/ventes/documents/{$bl->id}/confirmer")
            ->assertCreated();

        // The BL is invoiced, and an invoice now hangs off it.
        $this->assertSame('delivered', $bl->fresh()->status);
        $this->assertDatabaseHas('document_headers', [
            'parent_id'     => $bl->id,
            'document_type' => 'InvoiceSale',
        ]);

        // Auto-confirmation applies the pending movements: the goods left.
        $this->assertEquals(96, (float) WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('stockLevel'));
    }

    public function test_invoicing_a_delivery_note_twice_is_refused(): void
    {
        $bl = $this->makeNote('DeliveryNote', 'customer');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/ventes/documents/{$bl->id}/confirmer")
            ->assertCreated();

        // Now 'delivered' — outside the statuses the endpoint accepts.
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/ventes/documents/{$bl->id}/confirmer")
            ->assertStatus(422);
    }

    public function test_only_a_delivery_note_can_be_invoiced_this_way(): void
    {
        $quote = DocumentHeader::factory()->quote()->create(['status' => 'draft']);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/ventes/documents/{$quote->id}/confirmer")
            ->assertStatus(422);
    }

    // ── Achats : BR brouillon → facture fournisseur ──────────────

    public function test_invoicing_a_draft_receipt_note_confirms_it_first(): void
    {
        $br = $this->makeNote('ReceiptNotePurchase', 'supplier');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/achats/documents/{$br->id}/confirmer-facture")
            ->assertCreated();

        $this->assertSame('received', $br->fresh()->status);
        $this->assertDatabaseHas('document_headers', [
            'parent_id'     => $br->id,
            'document_type' => 'InvoicePurchase',
        ]);

        // A receipt note brings goods in.
        $this->assertEquals(104, (float) WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('stockLevel'));
    }

    public function test_only_a_receipt_note_can_be_invoiced_this_way(): void
    {
        $order = DocumentHeader::factory()->create([
            'document_type' => 'PurchaseOrder',
            'status'        => 'draft',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/achats/documents/{$order->id}/confirmer-facture")
            ->assertStatus(422);
    }
}
