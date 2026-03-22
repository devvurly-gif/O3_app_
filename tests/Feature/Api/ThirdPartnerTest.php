<?php

namespace Tests\Feature\Api;

use App\Models\ThirdPartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThirdPartnerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_index_returns_paginated_partners(): void
    {
        ThirdPartner::factory()->count(5)->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/third-partners')
             ->assertOk()
             ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_index_filters_by_role(): void
    {
        ThirdPartner::factory()->customer()->count(3)->create();
        ThirdPartner::factory()->supplier()->count(2)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/third-partners?role=supplier');

        $this->assertCount(2, $response->json('data'));
    }

    public function test_store_creates_partner(): void
    {
        $payload = [
            'tp_title' => 'Client Test SARL',
            'tp_Role'  => 'customer',
            'tp_email' => 'client@test.com',
            'tp_phone' => '0600000000',
        ];

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/third-partners', $payload)
             ->assertCreated();

        $this->assertDatabaseHas('third_partners', ['tp_title' => 'Client Test SARL']);
    }

    public function test_update_modifies_partner(): void
    {
        $partner = ThirdPartner::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->putJson("/api/third-partners/{$partner->id}", ['tp_title' => 'Nouveau Nom'])
             ->assertOk();

        $this->assertDatabaseHas('third_partners', ['id' => $partner->id, 'tp_title' => 'Nouveau Nom']);
    }

    public function test_delete_soft_deletes_partner(): void
    {
        $partner = ThirdPartner::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->deleteJson("/api/third-partners/{$partner->id}")
             ->assertSuccessful();

        $this->assertSoftDeleted('third_partners', ['id' => $partner->id]);
    }

    public function test_cashier_cannot_manage_partners(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/third-partners', ['tp_title' => 'Test'])
             ->assertForbidden();
    }
}
