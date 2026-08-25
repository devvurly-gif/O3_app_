<?php

namespace Tests\Feature\Api;

use App\Models\CashAccount;
use App\Models\CashCategory;
use App\Models\CashRecurrence;
use App\Models\CashTransaction;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\User;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

class TreasuryTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private CashAccount $caisse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin  = User::factory()->admin()->create();
        // Les comptes par défaut sont créés par la migration du module.
        $this->caisse = CashAccount::where('ca_payment_method', 'cash')->firstOrFail();
    }

    private function expense(array $overrides = []): CashTransaction
    {
        return CashTransaction::create(array_merge([
            'cash_account_id' => $this->caisse->id,
            'ct_direction'    => 'out',
            'ct_amount'       => 500,
            'ct_date'         => '2026-08-10',
            'ct_label'        => 'Carburant',
        ], $overrides));
    }

    /** Règlement encaissé sur une facture de vente : entrée de caisse. */
    private function salePayment(float $amount = 1000, string $method = 'cash'): Payment
    {
        $doc = DocumentHeader::factory()->invoice()->create(['user_id' => $this->admin->id]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ttc'          => $amount,
            'amount_due'         => $amount,
        ]);

        return Payment::factory()->create([
            'document_header_id' => $doc->id,
            'amount'             => $amount,
            'method'             => $method,
            'paid_at'            => '2026-08-11',
        ]);
    }

    /** Règlement d'une facture d'achat : sortie de caisse. */
    private function purchasePayment(float $amount = 400, string $method = 'cash'): Payment
    {
        $doc = DocumentHeader::factory()->create([
            'user_id'       => $this->admin->id,
            'document_type' => 'InvoicePurchase',
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ttc'          => $amount,
            'amount_due'         => $amount,
        ]);

        return Payment::factory()->create([
            'document_header_id' => $doc->id,
            'amount'             => $amount,
            'method'             => $method,
            'paid_at'            => '2026-08-12',
        ]);
    }

    // ── Saisie ────────────────────────────────────────────────────

    public function test_store_creates_expense_with_generated_code(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/cash-transactions', [
                'cash_account_id' => $this->caisse->id,
                'ct_direction'    => 'out',
                'ct_amount'       => 1200,
                'ct_date'         => '2026-08-01',
                'ct_label'        => 'Loyer août',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('cash_transactions', ['ct_label' => 'Loyer août', 'ct_amount' => 1200]);
        $this->assertStringStartsWith('TRZ-', $response->json('ct_code'));
    }

    public function test_store_rejects_category_of_the_wrong_direction(): void
    {
        $loyer = CashCategory::where('cc_direction', 'out')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/cash-transactions', [
                'cash_account_id'  => $this->caisse->id,
                'cash_category_id' => $loyer->id,
                'ct_direction'     => 'in',
                'ct_amount'        => 100,
                'ct_date'          => '2026-08-01',
                'ct_label'         => 'Erreur de sens',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cash_category_id']);
    }

    public function test_destroy_cancels_instead_of_deleting(): void
    {
        $transaction = $this->expense();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/cash-transactions/{$transaction->id}")
            ->assertOk();

        $this->assertDatabaseHas('cash_transactions', [
            'id'        => $transaction->id,
            'ct_status' => 'cancelled',
        ]);
    }

    public function test_cancelled_entry_leaves_the_balance(): void
    {
        $transaction = $this->expense(['ct_amount' => 900]);

        $before = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/treasury/summary')->json('total_out');

        $transaction->update(['ct_status' => 'cancelled']);

        $after = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/treasury/summary')->json('total_out');

        $this->assertSame(900.0, round($before - $after, 2));
    }

    // ── Synthèse ──────────────────────────────────────────────────

    public function test_summary_merges_manual_entries_and_document_payments(): void
    {
        $this->expense(['ct_amount' => 500]);        // dépense manuelle
        $this->salePayment(1000);                     // encaissement client
        $this->purchasePayment(400);                  // règlement fournisseur

        $summary = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/treasury/summary')
            ->assertOk()
            ->json();

        $this->assertSame(1000.0, (float) $summary['total_in']);
        $this->assertSame(900.0, (float) $summary['total_out']);
        $this->assertSame(100.0, (float) $summary['net']);
        $this->assertSame(500.0, (float) $summary['manual_out']);
        $this->assertSame(400.0, (float) $summary['payments_out']);
    }

    public function test_credit_payments_are_ignored_as_they_are_not_cash(): void
    {
        $this->salePayment(1000, 'credit');

        $summary = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/treasury/summary')->json();

        $this->assertSame(0.0, (float) $summary['total_in']);
    }

    public function test_account_balance_includes_initial_manual_and_payments(): void
    {
        $this->caisse->update(['ca_initial_balance' => 2000]);
        $this->expense(['ct_amount' => 500]);
        $this->salePayment(1000);

        $accounts = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/cash-accounts')->assertOk()->json();

        $caisse = collect($accounts)->firstWhere('id', $this->caisse->id);

        $this->assertSame(2500.0, (float) $caisse['balance']);
    }

    public function test_summary_respects_the_period(): void
    {
        $this->expense(['ct_amount' => 300, 'ct_date' => '2026-07-15']);
        $this->expense(['ct_amount' => 700, 'ct_date' => '2026-08-15']);

        $summary = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/treasury/summary?from=2026-08-01&to=2026-08-31')->json();

        $this->assertSame(700.0, (float) $summary['total_out']);
    }

    // ── Journal ───────────────────────────────────────────────────

    public function test_journal_lists_both_sources(): void
    {
        $this->expense();
        $this->salePayment();

        $journal = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/treasury/journal')
            ->assertOk()
            ->json('data');

        $sources = collect($journal)->pluck('source')->unique()->sort()->values()->all();

        $this->assertSame(['manual', 'payment'], $sources);
    }

    public function test_journal_can_be_filtered_to_manual_entries(): void
    {
        $this->expense();
        $this->salePayment();

        $journal = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/treasury/journal?source=manual')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $journal);
        $this->assertSame('manual', $journal[0]['source']);
    }

    // ── Virements ─────────────────────────────────────────────────

    public function test_transfer_creates_two_linked_entries_that_cancel_out(): void
    {
        $banque = CashAccount::where('ca_payment_method', 'bank_transfer')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/cash-transfers', [
                'from_account_id' => $this->caisse->id,
                'to_account_id'   => $banque->id,
                'amount'          => 5000,
                'date'            => '2026-08-20',
                'label'           => 'Versement banque',
            ])
            ->assertCreated();

        $this->assertSame(2, CashTransaction::whereNotNull('ct_transfer_group')->count());

        $summary = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/treasury/summary')->json();

        $this->assertSame(0.0, (float) $summary['net']);
    }

    public function test_transfer_refuses_the_same_account_on_both_sides(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/cash-transfers', [
                'from_account_id' => $this->caisse->id,
                'to_account_id'   => $this->caisse->id,
                'amount'          => 100,
                'date'            => '2026-08-20',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from_account_id']);
    }

    public function test_cancelling_one_half_of_a_transfer_cancels_the_other(): void
    {
        $banque = CashAccount::where('ca_payment_method', 'bank_transfer')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/cash-transfers', [
                'from_account_id' => $this->caisse->id,
                'to_account_id'   => $banque->id,
                'amount'          => 5000,
                'date'            => '2026-08-20',
            ]);

        $half = CashTransaction::whereNotNull('ct_transfer_group')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/cash-transactions/{$half->id}")
            ->assertOk();

        $this->assertSame(2, CashTransaction::where('ct_status', 'cancelled')->count());
    }

    // ── Récurrences ───────────────────────────────────────────────

    public function test_recurrence_catches_up_every_missed_occurrence(): void
    {
        CashRecurrence::create([
            'cr_label'        => 'Loyer mensuel',
            'cr_direction'    => 'out',
            'cr_amount'       => 3000,
            'cash_account_id' => $this->caisse->id,
            'cr_frequency'    => 'monthly',
            'cr_anchor_day'   => 5,
            'cr_start_date'   => '2026-06-05',
            'cr_next_run_at'  => '2026-06-05',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/cash-recurrences/run?up_to=2026-08-31')
            ->assertOk();

        // Juin, juillet, août : trois loyers, pas un seul.
        $this->assertSame(3, CashTransaction::whereNotNull('cash_recurrence_id')->count());
        $this->assertSame('2026-09-05', CashRecurrence::first()->cr_next_run_at->toDateString());
    }

    public function test_recurrence_stops_at_its_end_date(): void
    {
        CashRecurrence::create([
            'cr_label'        => 'Abonnement',
            'cr_direction'    => 'out',
            'cr_amount'       => 200,
            'cash_account_id' => $this->caisse->id,
            'cr_frequency'    => 'monthly',
            'cr_anchor_day'   => 1,
            'cr_start_date'   => '2026-06-01',
            'cr_end_date'     => '2026-07-01',
            'cr_next_run_at'  => '2026-06-01',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/cash-recurrences/run?up_to=2026-12-31')
            ->assertOk();

        $this->assertSame(2, CashTransaction::whereNotNull('cash_recurrence_id')->count());
    }

    // ── Accès ─────────────────────────────────────────────────────

    public function test_cashier_can_record_an_expense_but_not_create_an_account(): void
    {
        $cashier = User::factory()->create(['role_id' => $this->roleId('cashier')]);

        $this->actingAs($cashier, 'sanctum')
            ->postJson('/api/cash-transactions', [
                'cash_account_id' => $this->caisse->id,
                'ct_direction'    => 'out',
                'ct_amount'       => 80,
                'ct_date'         => '2026-08-02',
                'ct_label'        => 'Taxi',
            ])
            ->assertCreated();

        $this->actingAs($cashier, 'sanctum')
            ->postJson('/api/cash-accounts', ['ca_title' => 'Coffre'])
            ->assertForbidden();
    }

    private function roleId(string $name): int
    {
        return \App\Models\Role::firstOrCreate(
            ['name' => $name],
            ['display_name' => ucfirst($name)]
        )->id;
    }
}
