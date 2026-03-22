<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\ThirdPartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function createDocumentWithRelations(string $type = 'Invoice'): DocumentHeader
    {
        $partner = ThirdPartner::factory()->create();
        $doc     = DocumentHeader::factory()->state([
            'document_type'  => $type,
            'user_id'        => $this->admin->id,
            'thirdPartner_id'=> $partner->id,
        ])->create();

        DocumentFooter::factory()->create(['document_header_id' => $doc->id]);
        DocumentLigne::factory()->count(2)->create(['document_header_id' => $doc->id]);

        return $doc;
    }

    public function test_download_returns_pdf_content_type(): void
    {
        $doc = $this->createDocumentWithRelations();

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->get("/api/documents/{$doc->id}/pdf/download");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_stream_returns_inline_pdf(): void
    {
        $doc = $this->createDocumentWithRelations();

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->get("/api/documents/{$doc->id}/pdf/stream");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_download_invoice_pdf(): void
    {
        $doc = $this->createDocumentWithRelations('Invoice');

        $this->actingAs($this->admin, 'sanctum')
             ->get("/api/documents/{$doc->id}/pdf/download")
             ->assertOk();
    }

    public function test_download_quote_pdf(): void
    {
        $doc = $this->createDocumentWithRelations('Quote');

        $this->actingAs($this->admin, 'sanctum')
             ->get("/api/documents/{$doc->id}/pdf/download")
             ->assertOk();
    }

    public function test_download_delivery_note_pdf(): void
    {
        $doc = $this->createDocumentWithRelations('DeliveryNote');

        $this->actingAs($this->admin, 'sanctum')
             ->get("/api/documents/{$doc->id}/pdf/download")
             ->assertOk();
    }

    public function test_download_purchase_order_pdf(): void
    {
        $doc = $this->createDocumentWithRelations('PurchaseOrder');

        $this->actingAs($this->admin, 'sanctum')
             ->get("/api/documents/{$doc->id}/pdf/download")
             ->assertOk();
    }

    public function test_unauthenticated_cannot_access_pdf(): void
    {
        $this->getJson("/api/documents/1/pdf/download")
             ->assertUnauthorized();
    }

    public function test_any_role_can_access_pdf(): void
    {
        $cashier = User::factory()->cashier()->create();
        $doc     = $this->createDocumentWithRelations();

        $this->actingAs($cashier, 'sanctum')
             ->get("/api/documents/{$doc->id}/pdf/stream")
             ->assertOk();
    }
}
