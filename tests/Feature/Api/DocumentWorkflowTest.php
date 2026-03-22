<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ThirdPartner $customer;
    private ThirdPartner $supplier;
    private DocumentIncrementor $incrementor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin       = User::factory()->admin()->create();
        $this->customer    = ThirdPartner::factory()->create(['tp_Role' => 'customer']);
        $this->supplier    = ThirdPartner::factory()->create(['tp_Role' => 'supplier']);
        $this->incrementor = DocumentIncrementor::factory()->create();
    }

    // ── Full sale workflow: create → add lines → add footer → confirm ──

    public function test_full_sale_document_creation_workflow(): void
    {
        $product = Product::factory()->create();

        // 1. Create document
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/documents', [
                'document_incrementor_id' => $this->incrementor->id,
                'document_type'           => 'InvoiceSale',
                'document_title'          => 'Facture Vente Test',
                'thirdPartner_id'         => $this->customer->id,
                'company_role'            => 'customer',
                'status'                  => 'draft',
                'issued_at'               => now()->format('Y-m-d'),
            ]);

        $response->assertCreated();
        $docId = $response->json('id');

        // 2. Add line
        $lineResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/documents/{$docId}/lines", [
                'product_id'  => $product->id,
                'designation' => $product->p_title,
                'quantity'    => 5,
                'unit_price'  => 100,
                'tax_rate'    => 20,
                'discount'    => 0,
            ]);

        $lineResponse->assertCreated();

        // 3. Add footer
        $footerResponse = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/documents/{$docId}/footer", [
                'total_ht'       => 500,
                'total_discount' => 0,
                'total_tax'      => 100,
                'total_ttc'      => 600,
                'amount_paid'    => 0,
                'amount_due'     => 600,
            ]);

        $footerResponse->assertOk();

        // 4. Confirm document
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/documents/{$docId}", ['status' => 'confirmed'])
            ->assertOk();

        $this->assertDatabaseHas('document_headers', [
            'id'     => $docId,
            'status' => 'confirmed',
        ]);
    }

    public function test_purchase_document_creation(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/documents', [
                'document_incrementor_id' => $this->incrementor->id,
                'document_type'           => 'PurchaseOrder',
                'document_title'          => 'Commande Fournisseur',
                'thirdPartner_id'         => $this->supplier->id,
                'company_role'            => 'supplier',
                'status'                  => 'draft',
                'issued_at'               => now()->format('Y-m-d'),
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('document_headers', [
            'document_type' => 'PurchaseOrder',
            'status'        => 'draft',
        ]);
    }

    public function test_document_line_crud(): void
    {
        $doc     = DocumentHeader::factory()->draft()->create(['user_id' => $this->admin->id]);
        $product = Product::factory()->create();

        // Create line
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/documents/{$doc->id}/lines", [
                'product_id'  => $product->id,
                'designation' => 'Test Product',
                'quantity'    => 10,
                'unit_price'  => 50,
                'tax_rate'    => 20,
                'discount'    => 5,
            ]);

        $response->assertCreated();
        $lineId = $response->json('id');

        // Update line
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/documents/{$doc->id}/lines/{$lineId}", [
                'quantity' => 20,
            ])
            ->assertOk();

        $this->assertDatabaseHas('document_lignes', [
            'id'       => $lineId,
            'quantity' => 20,
        ]);

        // Delete line
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/documents/{$doc->id}/lines/{$lineId}")
            ->assertNoContent();
    }

    public function test_document_status_transitions(): void
    {
        $doc = DocumentHeader::factory()->draft()->create(['user_id' => $this->admin->id]);

        // Draft → Confirmed
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/documents/{$doc->id}", ['status' => 'confirmed'])
            ->assertOk();

        $this->assertDatabaseHas('document_headers', ['id' => $doc->id, 'status' => 'confirmed']);
    }

    public function test_cancelled_document_is_excluded_from_index(): void
    {
        DocumentHeader::factory()->create(['user_id' => $this->admin->id, 'status' => 'confirmed']);
        DocumentHeader::factory()->create(['user_id' => $this->admin->id, 'status' => 'cancelled']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/documents?status=confirmed');

        foreach ($response->json('data') as $doc) {
            $this->assertEquals('confirmed', $doc['status']);
        }
    }

    public function test_document_requires_incrementor(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/documents', [
                'document_type'  => 'InvoiceSale',
                'document_title' => 'Missing Incrementor',
                'status'         => 'draft',
            ])
            ->assertUnprocessable();
    }
}
