<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_import_products_validates_file_required(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/import/products', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['file']);
    }

    public function test_import_products_rejects_invalid_mime(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/import/products', ['file' => $file])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['file']);
    }

    public function test_import_third_partners_validates_file_required(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/import/third-partners', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['file']);
    }

    public function test_import_third_partners_rejects_invalid_mime(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/import/third-partners', ['file' => $file])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['file']);
    }

    public function test_cashier_cannot_import(): void
    {
        $cashier = User::factory()->cashier()->create();
        $file    = UploadedFile::fake()->create('products.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/import/products', ['file' => $file])
             ->assertForbidden();
    }

    public function test_manager_can_import(): void
    {
        Excel::fake();

        $manager = User::factory()->manager()->create();
        $file    = UploadedFile::fake()->create('products.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($manager, 'sanctum')
             ->postJson('/api/import/products', ['file' => $file])
             ->assertSuccessful();
    }

    // ── Categories Import ──────────────────────────────────────────

    public function test_import_categories_validates_file_required(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/import/categories', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['file']);
    }

    public function test_import_categories_rejects_invalid_mime(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/import/categories', ['file' => $file])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['file']);
    }

    public function test_cashier_cannot_import_categories(): void
    {
        $cashier = User::factory()->cashier()->create();
        $file    = UploadedFile::fake()->create('categories.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/import/categories', ['file' => $file])
             ->assertForbidden();
    }

    public function test_manager_can_import_categories(): void
    {
        Excel::fake();

        $manager = User::factory()->manager()->create();
        $file    = UploadedFile::fake()->create('categories.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($manager, 'sanctum')
             ->postJson('/api/import/categories', ['file' => $file])
             ->assertSuccessful();
    }

    // ── Brands Import ──────────────────────────────────────────────

    public function test_import_brands_validates_file_required(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/import/brands', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['file']);
    }

    public function test_import_brands_rejects_invalid_mime(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/import/brands', ['file' => $file])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['file']);
    }

    public function test_cashier_cannot_import_brands(): void
    {
        $cashier = User::factory()->cashier()->create();
        $file    = UploadedFile::fake()->create('brands.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/import/brands', ['file' => $file])
             ->assertForbidden();
    }

    public function test_manager_can_import_brands(): void
    {
        Excel::fake();

        $manager = User::factory()->manager()->create();
        $file    = UploadedFile::fake()->create('brands.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($manager, 'sanctum')
             ->postJson('/api/import/brands', ['file' => $file])
             ->assertSuccessful();
    }
}
