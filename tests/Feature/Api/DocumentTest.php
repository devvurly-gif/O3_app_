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

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    // ── Index ────────────────────────────────────────────────────

    public function test_index_returns_paginated_documents(): void
    {
        DocumentHeader::factory()->count(5)->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/documents')
             ->assertOk()
             ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_index_filters_by_document_type(): void
    {
        DocumentHeader::factory()->invoice()->create(['user_id' => $this->admin->id]);
        DocumentHeader::factory()->quote()->create(['user_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/documents?document_type=InvoiceSale');

        $this->assertCount(1, $response->json('data'));
    }

    // ── Show ─────────────────────────────────────────────────────

    public function test_show_returns_document_with_relations(): void
    {
        $doc = DocumentHeader::factory()->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin, 'sanctum')
             ->getJson("/api/documents/{$doc->id}")
             ->assertOk()
             ->assertJsonFragment(['id' => $doc->id]);
    }

    // ── Store ────────────────────────────────────────────────────

    public function test_store_creates_document(): void
    {
        $incrementor = DocumentIncrementor::factory()->create();
        $partner     = ThirdPartner::factory()->create();

        $payload = [
            'document_incrementor_id' => $incrementor->id,
            'document_type'           => 'Invoice',
            'document_title'          => 'Facture Test',
            'thirdPartner_id'         => $partner->id,
            'company_role'            => 'customer',
            'status'                  => 'draft',
            'issued_at'               => now()->format('Y-m-d'),
        ];

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/documents', $payload)
             ->assertCreated();

        $this->assertDatabaseHas('document_headers', ['document_title' => 'Facture Test']);
    }

    // ── Update ───────────────────────────────────────────────────

    public function test_update_modifies_document(): void
    {
        $doc = DocumentHeader::factory()->draft()->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin, 'sanctum')
             ->putJson("/api/documents/{$doc->id}", ['status' => 'confirmed'])
             ->assertOk();

        $this->assertDatabaseHas('document_headers', ['id' => $doc->id, 'status' => 'confirmed']);
    }

    // ── Delete ───────────────────────────────────────────────────

    public function test_delete_soft_deletes_document(): void
    {
        $doc = DocumentHeader::factory()->draft()->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin, 'sanctum')
             ->deleteJson("/api/documents/{$doc->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('document_headers', ['id' => $doc->id]);
    }

    // ── PDF endpoints ────────────────────────────────────────────

    public function test_pdf_download_returns_pdf(): void
    {
        $partner = ThirdPartner::factory()->create();
        $doc = DocumentHeader::factory()->create([
            'user_id'        => $this->admin->id,
            'thirdPartner_id'=> $partner->id,
        ]);
        DocumentFooter::factory()->create(['document_header_id' => $doc->id]);
        DocumentLigne::factory()->create(['document_header_id' => $doc->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->get("/api/documents/{$doc->id}/pdf/download");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_stream_returns_inline_pdf(): void
    {
        $partner = ThirdPartner::factory()->create();
        $doc = DocumentHeader::factory()->create([
            'user_id'        => $this->admin->id,
            'thirdPartner_id'=> $partner->id,
        ]);
        DocumentFooter::factory()->create(['document_header_id' => $doc->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->get("/api/documents/{$doc->id}/pdf/stream");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    // ── Authorization ────────────────────────────────────────────

    public function test_warehouse_user_cannot_create_document(): void
    {
        $warehouse = User::factory()->warehouse()->create();

        $this->actingAs($warehouse, 'sanctum')
             ->postJson('/api/documents', ['document_title' => 'Test'])
             ->assertForbidden();
    }

    public function test_any_user_can_read_documents(): void
    {
        $cashier = User::factory()->cashier()->create();
        DocumentHeader::factory()->create(['user_id' => $this->admin->id]);

        $this->actingAs($cashier, 'sanctum')
             ->getJson('/api/documents')
             ->assertOk();
    }
}
