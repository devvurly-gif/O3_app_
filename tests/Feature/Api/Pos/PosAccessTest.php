<?php

namespace Tests\Feature\Api\Pos;

use App\Models\DocumentHeader;
use App\Models\User;
use Tests\Concerns\InteractsWithPos;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * The two gates in front of every POS route: the tenant feature flag
 * (`feature:pos`) and the per-action permissions (`permission:pos.*`).
 *
 * Both are configuration-driven and neither is exercised anywhere else in
 * the suite, so a regression in either would otherwise open the till to
 * roles that are not supposed to reach it.
 */
class PosAccessTest extends TestCase
{
    use RefreshTenantDatabase;
    use InteractsWithPos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTenant();
        $this->setUpPosTerminal();
    }

    // ── Feature flag ─────────────────────────────────────────────

    public function test_pos_is_unreachable_when_the_tenant_feature_is_off(): void
    {
        $this->fakeTenant(['pos_enabled' => false]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/pos/sessions/current')
            ->assertForbidden();
    }

    public function test_pos_is_unreachable_when_no_tenant_is_resolved(): void
    {
        $this->app->forgetInstance(\Stancl\Tenancy\Contracts\Tenant::class);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/pos/sessions/current')
            ->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/pos/sessions/current')->assertUnauthorized();
        $this->postJson('/api/pos/tickets', [])->assertUnauthorized();
    }

    // ── Permissions ──────────────────────────────────────────────

    public function test_cashier_without_pos_access_is_refused(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
            ->getJson('/api/pos/sessions/current')
            ->assertForbidden();
    }

    public function test_cashier_with_pos_access_reaches_the_till(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->grantPermissions($cashier, 'pos.access');

        // 204: authorised, simply no session open yet.
        $this->actingAs($cashier, 'sanctum')
            ->getJson('/api/pos/sessions/current')
            ->assertNoContent();
    }

    public function test_admin_bypasses_pos_permissions(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/pos/sessions/current')
            ->assertNoContent();
    }

    public function test_opening_a_session_requires_its_own_permission(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->grantPermissions($cashier, 'pos.access');

        $this->actingAs($cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', [
                'pos_terminal_id' => $this->terminal->id,
                'opening_cash'    => 200,
            ])
            ->assertForbidden();

        $this->grantPermissions($cashier, 'pos.open_session');

        $this->actingAs($cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', [
                'pos_terminal_id' => $this->terminal->id,
                'opening_cash'    => 200,
            ])
            ->assertCreated();
    }

    public function test_voiding_a_ticket_requires_a_permission_cashiers_do_not_get(): void
    {
        // RolePermissionSeeder grants cashiers pos.access, pos.open_session and
        // pos.close_session — deliberately not pos.void_ticket. This asserts the
        // route enforces that split rather than relying on the seeder alone.
        $cashier = User::factory()->cashier()->create();
        $this->grantPermissions($cashier, 'pos.access', 'pos.open_session', 'pos.close_session');

        // A real ticket: route-model binding runs before the permission
        // middleware, so a made-up id would 404 and prove nothing.
        $ticket = DocumentHeader::factory()->create([
            'document_type' => 'TicketSale',
            'status'        => 'paid',
            'user_id'       => $cashier->id,
        ]);

        $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/pos/tickets/{$ticket->id}/void")
            ->assertForbidden();
    }

    public function test_session_list_is_restricted_to_admin_and_manager(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->grantPermissions($cashier, 'pos.access');

        $this->actingAs($cashier, 'sanctum')
            ->getJson('/api/pos/sessions')
            ->assertForbidden();

        $manager = User::factory()->manager()->create();
        $this->grantPermissions($manager, 'pos.access');

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/pos/sessions')
            ->assertOk();
    }
}
