<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Login ────────────────────────────────────────────────────

    public function test_login_returns_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
                 ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertUnprocessable();
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $user = User::factory()->inactive()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/auth/login', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['email', 'password']);
    }

    // ── Me ───────────────────────────────────────────────────────

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/auth/me')
             ->assertOk()
             ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_me_returns_401_for_guest(): void
    {
        $this->getJson('/api/auth/me')
             ->assertUnauthorized();
    }

    // ── Logout ───────────────────────────────────────────────────

    public function test_logout_revokes_token(): void
    {
        $user  = User::factory()->create(['password' => bcrypt('secret123')]);
        $login = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $token = $login->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
             ->postJson('/api/auth/logout')
             ->assertOk()
             ->assertJsonFragment(['message' => 'Logged out successfully.']);
    }

    // ── Role middleware ──────────────────────────────────────────

    public function test_admin_can_access_admin_routes(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/users')
             ->assertOk();
    }

    public function test_cashier_cannot_access_admin_routes(): void
    {
        $user = User::factory()->cashier()->create();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/users')
             ->assertForbidden();
    }

    public function test_warehouse_cannot_create_documents(): void
    {
        $user = User::factory()->warehouse()->create();

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/documents', [])
             ->assertForbidden();
    }
}
