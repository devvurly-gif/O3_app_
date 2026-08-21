<?php

namespace Tests\Feature\Api\Pos;

use App\Models\PosSession;
use App\Models\PosTerminal;
use App\Models\User;
use Tests\Concerns\InteractsWithPos;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Opening, resuming and closing a till session.
 *
 * The closing figures matter beyond the POS screen: `expected_cash` is what
 * the cashier is held to at handover, and `cash_difference` is what lands in
 * the closing report emailed to admins.
 */
class PosSessionTest extends TestCase
{
    use RefreshTenantDatabase;
    use InteractsWithPos;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTenant();
        $this->setUpPosTerminal();
        $this->setUpPosIncrementors();
        $this->setUpComptoirCustomer();

        $this->cashier = User::factory()->cashier()->create();
        $this->grantPermissions(
            $this->cashier,
            'pos.access',
            'pos.open_session',
            'pos.close_session',
        );
    }

    // ── Opening ──────────────────────────────────────────────────

    public function test_opening_a_session_records_the_terminal_user_and_float(): void
    {
        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', [
                'pos_terminal_id' => $this->terminal->id,
                'opening_cash'    => 500,
            ]);

        $response->assertCreated()
            ->assertJsonPath('pos_terminal_id', $this->terminal->id)
            ->assertJsonPath('user_id', $this->cashier->id);

        $this->assertDatabaseHas('pos_sessions', [
            'pos_terminal_id' => $this->terminal->id,
            'user_id'         => $this->cashier->id,
            'opening_cash'    => 500,
            'closed_at'       => null,
        ]);
    }

    public function test_reopening_the_same_terminal_resumes_the_existing_session(): void
    {
        $session = $this->openSessionFor($this->cashier, 300);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', [
                'pos_terminal_id' => $this->terminal->id,
                'opening_cash'    => 999,
            ])
            ->assertCreated()
            ->assertJsonPath('id', $session->id);

        // Resuming must not open a second session nor reset the float.
        $this->assertSame(1, PosSession::count());
        $this->assertEquals(300, (float) $session->fresh()->opening_cash);
    }

    public function test_a_terminal_held_by_another_user_cannot_be_taken_over(): void
    {
        $other = User::factory()->cashier()->create();
        $this->openSessionFor($other);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', [
                'pos_terminal_id' => $this->terminal->id,
                'opening_cash'    => 100,
            ])
            ->assertStatus(422);
    }

    public function test_an_inactive_terminal_cannot_be_opened(): void
    {
        $disabled = PosTerminal::factory()->inactive()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', [
                'pos_terminal_id' => $disabled->id,
                'opening_cash'    => 100,
            ])
            ->assertStatus(422);
    }

    public function test_opening_validates_its_input(): void
    {
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_terminal_id', 'opening_cash']);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', [
                'pos_terminal_id' => 999999,
                'opening_cash'    => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_terminal_id', 'opening_cash']);
    }

    // ── Current session ──────────────────────────────────────────

    public function test_current_returns_the_open_session_and_nothing_once_closed(): void
    {
        $session = $this->openSessionFor($this->cashier);

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/pos/sessions/current')
            ->assertOk()
            ->assertJsonPath('id', $session->id);

        $session->update(['closed_at' => now()]);

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/pos/sessions/current')
            ->assertNoContent();
    }

    public function test_current_is_scoped_to_the_authenticated_cashier(): void
    {
        $other = User::factory()->cashier()->create();
        $this->openSessionFor($other);

        // A session open on the terminal, but not this cashier's.
        $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/pos/sessions/current')
            ->assertNoContent();
    }

    // ── Closing ──────────────────────────────────────────────────

    public function test_closing_expects_the_float_plus_cash_takings(): void
    {
        $session = $this->openSessionFor($this->cashier, 200);
        $product = $this->stockedProduct(salePrice: 100);

        // One cash sale of 100, one card sale of 100: only cash counts
        // toward the drawer.
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 100, 'method' => 'cash']],
            ))->assertCreated();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 100, 'method' => 'card']],
            ))->assertCreated();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/close", [
                'closing_cash' => 300,
            ])
            ->assertOk();

        $session->refresh();

        $this->assertEquals(300, (float) $session->expected_cash, 'float 200 + 100 cash');
        $this->assertEquals(300, (float) $session->closing_cash);
        $this->assertEquals(0, (float) $session->cash_difference);
        $this->assertNotNull($session->closed_at);
    }

    public function test_closing_records_a_drawer_shortfall(): void
    {
        $session = $this->openSessionFor($this->cashier, 200);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/close", [
                'closing_cash' => 150,
                'notes'        => 'Écart constaté au comptage',
            ])
            ->assertOk();

        $session->refresh();

        $this->assertEquals(200, (float) $session->expected_cash);
        $this->assertEquals(-50, (float) $session->cash_difference);
        $this->assertSame('Écart constaté au comptage', $session->notes);
    }

    public function test_a_closed_session_cannot_be_closed_again(): void
    {
        $session = $this->openSessionFor($this->cashier);
        $session->update(['closed_at' => now(), 'closing_cash' => 0]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/close", ['closing_cash' => 0])
            ->assertStatus(422);
    }

    public function test_closing_validates_its_input(): void
    {
        $session = $this->openSessionFor($this->cashier);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/close", ['closing_cash' => -5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['closing_cash']);
    }

    // ── Force close ──────────────────────────────────────────────

    public function test_admin_can_force_close_a_session_left_open(): void
    {
        $session = $this->openSessionFor($this->cashier, 200);
        $admin   = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/force-close")
            ->assertOk();

        $session->refresh();

        $this->assertNotNull($session->closed_at);
        $this->assertStringContainsString('forcée', (string) $session->notes);
    }

    public function test_a_cashier_cannot_force_close(): void
    {
        $session = $this->openSessionFor($this->cashier);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/force-close")
            ->assertForbidden();
    }

    public function test_force_closing_an_already_closed_session_is_rejected(): void
    {
        $session = $this->openSessionFor($this->cashier);
        $session->update(['closed_at' => now()]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/force-close")
            ->assertStatus(422);
    }
}
