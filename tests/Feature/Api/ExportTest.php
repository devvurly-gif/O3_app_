<?php

namespace Tests\Feature\Api;

use App\Models\DocumentHeader;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_export_products_returns_xlsx(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->get('/api/export/products');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
    }

    public function test_export_documents_returns_xlsx(): void
    {
        DocumentHeader::factory()->count(2)->create(['user_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->get('/api/export/documents');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type')
        );
    }

    public function test_export_third_partners_returns_xlsx(): void
    {
        ThirdPartner::factory()->count(2)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->get('/api/export/third-partners');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type')
        );
    }

    public function test_export_stock_mouvements_returns_xlsx(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
                         ->get('/api/export/stock-mouvements');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type')
        );
    }

    public function test_export_payments_returns_xlsx(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
                         ->get('/api/export/payments');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type')
        );
    }

    public function test_unauthenticated_user_cannot_export(): void
    {
        $this->getJson('/api/export/products')
             ->assertUnauthorized();
    }

    public function test_export_with_filters(): void
    {
        Product::factory()->create(['p_status' => true]);
        Product::factory()->create(['p_status' => false]);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->get('/api/export/products?p_status=1');

        $response->assertOk();
    }
}
