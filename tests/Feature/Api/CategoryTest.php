<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_index_returns_all_categories(): void
    {
        Category::factory()->count(4)->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/categories')
             ->assertOk()
             ->assertJsonCount(4);
    }

    public function test_show_returns_category(): void
    {
        $cat = Category::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson("/api/categories/{$cat->id}")
             ->assertOk()
             ->assertJsonFragment(['ctg_title' => $cat->ctg_title]);
    }

    public function test_store_creates_category(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/categories', ['ctg_title' => 'Electronique'])
             ->assertCreated();

        $this->assertDatabaseHas('categories', ['ctg_title' => 'Electronique']);
    }

    public function test_store_validates_required_title(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/categories', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['ctg_title']);
    }

    public function test_update_modifies_category(): void
    {
        $cat = Category::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->putJson("/api/categories/{$cat->id}", ['ctg_title' => 'Alimentation'])
             ->assertOk();

        $this->assertDatabaseHas('categories', ['id' => $cat->id, 'ctg_title' => 'Alimentation']);
    }

    public function test_delete_removes_category(): void
    {
        $cat = Category::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->deleteJson("/api/categories/{$cat->id}")
             ->assertNoContent();
    }

    public function test_cashier_cannot_create_category(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/categories', ['ctg_title' => 'Interdit'])
             ->assertForbidden();
    }

    public function test_any_user_can_list_categories(): void
    {
        $warehouse = User::factory()->warehouse()->create();

        $this->actingAs($warehouse, 'sanctum')
             ->getJson('/api/categories')
             ->assertOk();
    }
}
