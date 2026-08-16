<?php

namespace Tests\Feature\Api;

use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * confirmer-br / confirmer-bl answer { message, data: <document> }, unlike the
 * plain document endpoints that return the model at the top level. The detail
 * pages bind straight to what the store returns, so an unwrapped envelope
 * renders a blank document. These tests pin the shape both sides agreed on.
 */
class DocumentConfirmResponseTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function draftDocument(string $type, string $partnerRole): DocumentHeader
    {
        $warehouse = Warehouse::factory()->create();
        $product   = Product::factory()->create();

        // A delivery note exits stock: without a stocked warehouse the confirm
        // is rejected as "stock insuffisant" before it ever builds a response.
        WarehouseHasStock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id'   => $product->id,
            'stockLevel'   => 50,
        ]);

        $document = DocumentHeader::factory()->create([
            'document_incrementor_id' => DocumentIncrementor::factory()->create()->id,
            'document_type'           => $type,
            'thirdPartner_id'         => ThirdPartner::factory()->create(['tp_Role' => $partnerRole])->id,
            'warehouse_id'            => $warehouse->id,
            'status'                  => 'draft',
            'user_id'                 => $this->admin->id,
        ]);

        DocumentLigne::factory()->create([
            'document_header_id' => $document->id,
            'product_id'         => $product->id,
            'quantity'           => 3,
            'unit_price'         => 100,
        ]);

        return $document;
    }

    public function test_confirming_a_receipt_note_returns_the_document_under_data(): void
    {
        $br = $this->draftDocument('ReceiptNotePurchase', 'supplier');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/achats/documents/{$br->id}/confirmer-br")
            ->assertOk();

        $this->assertSame($br->id, $response->json('data.id'));
        $this->assertSame('confirmed', $response->json('data.status'));
        $this->assertNotNull($response->json('data.reference'));
        // The document must not also sit at the top level — the store unwraps
        // `data`, so a shape change here silently blanks the detail page.
        $this->assertNull($response->json('id'));
    }

    public function test_confirming_a_delivery_note_returns_the_document_under_data(): void
    {
        $bl = $this->draftDocument('DeliveryNote', 'customer');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/ventes/documents/{$bl->id}/confirmer-bl")
            ->assertOk();

        $this->assertSame($bl->id, $response->json('data.id'));
        $this->assertSame('confirmed', $response->json('data.status'));
        $this->assertNull($response->json('id'));
    }
}
