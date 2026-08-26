<?php

namespace Tests\Feature\Api\Pos;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\PosSession;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use Tests\Concerns\InteractsWithPos;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * L'argent d'une caisse n'entre en trésorerie qu'une fois le comptage endossé.
 *
 * Tant qu'une session n'est pas validée, ses encaissements restent hors du
 * journal : la recette théorique et les espèces réellement en tiroir peuvent
 * différer, et c'est le responsable qui tranche.
 */
class SessionValidationTest extends TestCase
{
    use RefreshTenantDatabase;
    use InteractsWithPos;

    private User $cashier;
    private User $manager;
    private ThirdPartner $comptoir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTenant();
        $this->setUpPosTerminal();
        $this->setUpPosIncrementors();
        $this->comptoir = $this->setUpComptoirCustomer();

        DocumentIncrementor::firstOrCreate(
            ['di_model' => 'InvoiceSale'],
            ['di_title' => 'Facture Vente', 'di_domain' => 'sales', 'template' => 'FV-{NNNN}',
             'nextTrick' => 1, 'status' => true, 'operatorSens' => 'out'],
        );

        CashAccount::firstOrCreate(
            ['ca_payment_method' => 'cash'],
            ['ca_title' => 'Caisse espèces', 'ca_type' => 'cash', 'ca_status' => true],
        );

        $this->cashier = User::factory()->cashier()->create();
        $this->grantPermissions($this->cashier, 'pos.access', 'pos.open_session', 'pos.close_session', 'pos.void_ticket');

        $this->manager = User::factory()->create([
            'role_id' => \App\Models\Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Gestionnaire'])->id,
        ]);
        // Les routes POS sont derriere pos.access, y compris pour un manager.
        $this->grantPermissions($this->manager, 'pos.access', 'pos.void_ticket');
    }

    private function sellAndClose(float $amount, float $closingCash): PosSession
    {
        $sessionId = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', ['pos_terminal_id' => $this->terminal->id, 'opening_cash' => 0])
            ->json('id');

        $product = $this->stockedProduct(salePrice: $amount, stock: 100);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 1, 'unit_price' => $amount, 'tax_percent' => 0]],
                [['amount' => $amount, 'method' => 'cash']],
            ))->assertCreated();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/sessions/{$sessionId}/close", ['closing_cash' => $closingCash])
            ->assertOk();

        return PosSession::findOrFail($sessionId);
    }

    private function summary(): array
    {
        return $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/treasury/summary')->assertOk()->json();
    }

    // ── Avant validation ──────────────────────────────────────────

    public function test_pos_takings_stay_out_of_the_treasury_until_validation(): void
    {
        $this->sellAndClose(500, 500);

        // La vente est encaissee, mais la caisse n'est pas encore endossee.
        $this->assertSame(0.0, (float) $this->summary()['total_in']);
    }

    // ── Validation ────────────────────────────────────────────────

    public function test_validation_posts_the_takings_to_the_treasury(): void
    {
        $session = $this->sellAndClose(500, 500);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/valider", [])
            ->assertOk();

        $this->assertSame(500.0, (float) $this->summary()['total_in']);

        $entry = CashTransaction::where('ct_reference', 'POS-' . $session->id)->firstOrFail();
        $this->assertSame('in', $entry->ct_direction);
        $this->assertSame('cash', $entry->ct_method);
        $this->assertStringContainsString('Recette caisse', $entry->ct_label);
    }

    public function test_a_session_is_validated_only_once(): void
    {
        $session = $this->sellAndClose(500, 500);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/valider", [])->assertOk();

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/valider", [])->assertStatus(422);

        $this->assertSame(500.0, (float) $this->summary()['total_in']);
    }

    public function test_an_open_session_cannot_be_validated(): void
    {
        $sessionId = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', ['pos_terminal_id' => $this->terminal->id, 'opening_cash' => 0])
            ->json('id');

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pos/sessions/{$sessionId}/valider", [])
            ->assertStatus(422);
    }

    public function test_the_cashier_cannot_validate_his_own_till(): void
    {
        $session = $this->sellAndClose(500, 500);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/valider", [])
            ->assertForbidden();
    }

    // ── Ce dont l'ecran a besoin ──────────────────────────────────

    public function test_the_pending_queue_lists_only_unvalidated_closed_tills(): void
    {
        $validee = $this->sellAndClose(500, 500);
        $attente = $this->sellAndClose(300, 300);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pos/sessions/{$validee->id}/valider", [])->assertOk();

        $rows = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/pos/sessions?pending_validation=1')
            ->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame($attente->id, $rows[0]['id']);
    }

    public function test_the_detail_carries_the_count_and_the_validation_state(): void
    {
        $session = $this->sellAndClose(500, 470);

        $detail = $this->actingAs($this->manager, 'sanctum')
            ->getJson("/api/pos/sessions/{$session->id}/live-stats")
            ->assertOk()->json();

        $this->assertSame(500.0, (float) $detail['session']['expected_cash']);
        $this->assertSame(470.0, (float) $detail['session']['closing_cash']);
        $this->assertSame(-30.0, (float) $detail['session']['cash_difference']);
        $this->assertNull($detail['session']['validated_at']);
        $this->assertSame(500.0, (float) $detail['stats']['payments_by_method']['cash']);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/valider", ['variance_reason' => 'Erreur de rendu.'])
            ->assertOk();

        $detail = $this->actingAs($this->manager, 'sanctum')
            ->getJson("/api/pos/sessions/{$session->id}/live-stats")->json();

        $this->assertNotNull($detail['session']['validated_at']);
        $this->assertSame($this->manager->name, $detail['session']['validated_by']);
        $this->assertSame('Erreur de rendu.', $detail['session']['variance_reason']);
        $this->assertSame(30.0, (float) $detail['session']['variance_transaction']['amount']);
    }

    // ── Écart de caisse ───────────────────────────────────────────

    public function test_a_shortfall_is_refused_without_a_written_explanation(): void
    {
        $session = $this->sellAndClose(500, 450);   // 50 manquants

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/valider", [])
            ->assertStatus(422);

        $this->assertFalse($session->fresh()->isValidated());
        $this->assertSame(0.0, (float) $this->summary()['total_in']);
    }

    public function test_a_shortfall_is_written_as_a_negative_entry_with_its_report(): void
    {
        $session = $this->sellAndClose(500, 450);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/valider", [
                'variance_reason' => 'Rendu de monnaie errone sur le ticket TIC-0001, constate en fin de journee.',
            ])
            ->assertOk();

        $variance = $session->fresh()->varianceTransaction;

        $this->assertNotNull($variance);
        $this->assertSame('out', $variance->ct_direction);
        $this->assertSame(50.0, (float) $variance->ct_amount);
        $this->assertStringContainsString('Manque de caisse', $variance->ct_label);
        $this->assertStringContainsString('Rendu de monnaie errone', $variance->ct_notes);
        $this->assertSame('Ecart de caisse', $variance->category?->cc_title);

        // 500 encaisses, 50 manquants : la tresorerie porte les deux.
        $summary = $this->summary();
        $this->assertSame(500.0, (float) $summary['total_in']);
        $this->assertSame(50.0, (float) $summary['total_out']);
        $this->assertSame(450.0, (float) $summary['net']);
    }

    public function test_a_surplus_is_written_as_a_positive_entry(): void
    {
        $session = $this->sellAndClose(500, 530);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/valider", [
                'variance_reason' => 'Fond de caisse initial sous-declare a l ouverture.',
            ])
            ->assertOk();

        $variance = $session->fresh()->varianceTransaction;

        $this->assertSame('in', $variance->ct_direction);
        $this->assertSame(30.0, (float) $variance->ct_amount);
        $this->assertStringContainsString('Excedent de caisse', $variance->ct_label);
    }

    // ── Verrouillage après clôture ────────────────────────────────

    public function test_the_cashier_can_no_longer_void_a_ticket_of_a_closed_till(): void
    {
        $this->sellAndClose(500, 500);

        $ticket = DocumentHeader::where('document_type', 'TicketSale')->firstOrFail();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/tickets/{$ticket->id}/void")
            ->assertForbidden();

        $this->assertSame('paid', $ticket->fresh()->status);
    }

    public function test_a_manager_keeps_the_right_to_correct(): void
    {
        $this->sellAndClose(500, 500);

        $ticket = DocumentHeader::where('document_type', 'TicketSale')->firstOrFail();

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pos/tickets/{$ticket->id}/void")
            ->assertOk();

        $this->assertSame('cancelled', $ticket->fresh()->status);
    }

    // ── BL en compte ──────────────────────────────────────────────

    /**
     * Une vente à crédit ne passe pas par la caisse : elle devient un bon de
     * livraison, hors du comptage et hors de la facture de clôture.
     */
    public function test_a_credit_sale_stays_out_of_the_till_count(): void
    {
        Setting::updateOrCreate(['st_domain' => 'pos', 'st_key' => 'facture_cloture'], ['st_value' => 'true']);

        $client = ThirdPartner::factory()->create([
            'tp_title'     => 'Client en compte',
            'tp_Role'      => 'customer',
            'type_compte'  => 'en_compte',
            'seuil_credit' => 5000,
        ]);

        $sessionId = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/sessions/open', ['pos_terminal_id' => $this->terminal->id, 'opening_cash' => 0])
            ->json('id');

        $product = $this->stockedProduct(salePrice: 800, stock: 100);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'quantity' => 1, 'unit_price' => 800, 'tax_percent' => 0]],
                [['amount' => 800, 'method' => 'credit']],
                $client->id,
            ))->assertCreated();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/pos/sessions/{$sessionId}/close", ['closing_cash' => 0])
            ->assertOk();

        $session = PosSession::findOrFail($sessionId);

        // Ni dans le comptage, ni transformee en ticket.
        $this->assertSame(0.0, (float) $session->expected_cash);
        $this->assertSame(0.0, (float) $session->cash_difference);
        $this->assertSame(0, DocumentHeader::where('document_type', 'TicketSale')->count());
        $this->assertSame(1, DocumentHeader::where('document_type', 'DeliveryNote')->count());

        // Et rien n'entre en tresorerie a la validation : le client doit encore.
        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pos/sessions/{$session->id}/valider", [])->assertOk();

        $this->assertSame(0.0, (float) $this->summary()['total_in']);
    }
}
