<?php

namespace Tests\Feature\Api;

use App\Models\AgentEvent;
use App\Models\CashAccount;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\PosTerminal;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Les lectures de l'orchestrateur sur l'activité : point de la journée, validations en attente, ventes, factures
 * échues, devis sans suite, bons de livraison non facturés, encaissements, soldes et sessions de caisse.
 * Lecture seule et sans modèle de langage : aucune requête HTTP ne doit partir.
 */
class OrchestratorBusinessTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $atlas;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));   // mercredi 14/10
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->atlas = ThirdPartner::factory()->create(['tp_title' => 'Quincaillerie Atlas']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function say(string $text): array
    {
        $reply = $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
        Http::assertNothingSent();   // aucune donnée ne part chez un fournisseur d'IA

        return $reply;
    }

    private function doc(string $type, string $status, string $day, float $ttc, float $due = 0, ?string $dueAt = null, string $ref = null, ?ThirdPartner $tp = null): DocumentHeader
    {
        $d = DocumentHeader::factory()->create([
            'document_type' => $type, 'status' => $status, 'issued_at' => $day, 'due_at' => $dueAt, 'thirdPartner_id' => ($tp ?? $this->atlas)->id,
            'reference' => $ref ?? 'D-' . fake()->unique()->numerify('####'),
        ]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => round($ttc / 1.2, 2), 'total_ttc' => $ttc, 'amount_paid' => $ttc - $due, 'amount_due' => $due]);

        return $d;
    }

    public function test_sales_for_the_month_exclude_drafts_and_cancelled_and_compare_with_the_previous_period(): void
    {
        $this->doc('InvoiceSale', 'confirmed', '2026-10-05', 1200);
        $this->doc('InvoiceSale', 'paid', '2026-10-10', 600);
        $this->doc('InvoiceSale', 'draft', '2026-10-11', 9999);
        $this->doc('InvoiceSale', 'cancelled', '2026-10-12', 9999);
        $this->doc('InvoiceSale', 'confirmed', '2026-09-20', 900);   // période précédente (mêmes 14 jours : 22/09 → 05/10 non, 9/20 hors)
        $this->doc('CreditNoteSale', 'confirmed', '2026-10-08', 100);

        $r = $this->say('chiffre d\'affaires du mois');

        $this->assertStringContainsString('2 facture(s) ou ticket(s)', $r['body']);
        $this->assertStringContainsString('1 500,00 MAD HT, 1 800,00 MAD TTC', str_replace("\u{202f}", ' ', str_replace("\u{a0}", ' ', $r['body'])));
        $this->assertStringContainsString('Quincaillerie Atlas', $r['body']);
        $this->assertStringContainsString('Avoirs sur la période : 1', $r['body']);
        $this->assertStringNotContainsString('9 999', $r['body']);
    }

    public function test_sales_of_a_single_day_and_yesterday(): void
    {
        $this->doc('InvoiceSale', 'confirmed', '2026-10-14', 300);
        $this->doc('TicketSale', 'paid', '2026-10-14', 120);
        $this->doc('InvoiceSale', 'confirmed', '2026-10-13', 777);

        $this->assertStringContainsString('2 facture(s) ou ticket(s)', $this->say('ventes d\'aujourd\'hui')['body']);
        $this->assertStringContainsString('1 facture(s) ou ticket(s)', $this->say('ventes d\'hier')['body']);
        $this->assertStringContainsString('3 facture(s) ou ticket(s)', $this->say('ventes des 3 derniers jours')['body']);
        DocumentHeader::query()->delete();
        $this->assertStringContainsString('Aucune vente', $this->say('chiffre d\'affaires d\'hier')['body']);
    }

    public function test_overdue_invoices_are_listed_and_the_age_filter_works(): void
    {
        $this->doc('InvoiceSale', 'confirmed', '2026-08-01', 1200, 1200, '2026-08-31', 'FV-OLD');
        $this->doc('InvoiceSale', 'confirmed', '2026-10-01', 500, 500, '2026-10-10', 'FV-NEW');
        $this->doc('InvoiceSale', 'confirmed', '2026-10-01', 500, 500, '2026-11-10', 'FV-FUTURE');
        $this->doc('InvoiceSale', 'paid', '2026-08-01', 300, 0, '2026-08-31', 'FV-PAID');

        $all = $this->say('factures échues');
        $this->assertStringContainsString('2 facture(s) échue(s)', $all['body']);
        $this->assertStringContainsString('FV-OLD', $all['body']);
        $this->assertStringContainsString('FV-NEW', $all['body']);
        $this->assertStringNotContainsString('FV-FUTURE', $all['body']);
        $this->assertStringNotContainsString('FV-PAID', $all['body']);

        $old = $this->say('factures impayées depuis plus de 30 jours');
        $this->assertStringContainsString('1 facture(s) échue(s) depuis plus de 30 jour(s)', $old['body']);
        $this->assertStringContainsString('FV-OLD', $old['body']);
        $this->assertStringNotContainsString('FV-NEW', $old['body']);
    }

    public function test_stale_quotes_and_unbilled_deliveries(): void
    {
        $this->doc('QuoteSale', 'sent', '2026-09-20', 800, 0, null, 'DV-OLD');
        $this->doc('QuoteSale', 'sent', '2026-10-12', 800, 0, null, 'DV-RECENT');
        $this->doc('QuoteSale', 'converted', '2026-09-01', 800, 0, null, 'DV-DONE');
        $this->doc('DeliveryNote', 'delivered', '2026-10-01', 450, 0, null, 'BL-OPEN');
        $this->doc('DeliveryNote', 'converted', '2026-10-01', 450, 0, null, 'BL-BILLED');
        $this->doc('DeliveryNote', 'draft', '2026-10-01', 450, 0, null, 'BL-DRAFT');

        $q = $this->say('devis sans suite depuis 10 jours');
        $this->assertStringContainsString('DV-OLD', $q['body']);
        $this->assertStringNotContainsString('DV-RECENT', $q['body']);
        $this->assertStringNotContainsString('DV-DONE', $q['body']);

        $b = $this->say('bons de livraison non facturés');
        $this->assertStringContainsString('BL-OPEN', $b['body']);
        $this->assertStringNotContainsString('BL-BILLED', $b['body']);
        $this->assertStringNotContainsString('BL-DRAFT', $b['body']);
    }

    public function test_income_by_method_and_balances_and_cash_sessions(): void
    {
        $inv = $this->doc('InvoiceSale', 'partial', '2026-10-14', 1000, 400);
        Payment::factory()->create(['document_header_id' => $inv->id, 'amount' => 400, 'method' => 'cash', 'paid_at' => '2026-10-14']);
        Payment::factory()->create(['document_header_id' => $inv->id, 'amount' => 200, 'method' => 'cheque', 'paid_at' => '2026-10-14']);
        Payment::factory()->create(['document_header_id' => $inv->id, 'amount' => 999, 'method' => 'cash', 'paid_at' => '2026-10-01']);
        $purchase = $this->doc('InvoicePurchase', 'confirmed', '2026-10-14', 300, 300);
        Payment::factory()->create(['document_header_id' => $purchase->id, 'amount' => 150, 'method' => 'bank_transfer', 'paid_at' => '2026-10-14']);

        $income = $this->say('encaissements du jour');
        $text = str_replace(["\u{202f}", "\u{a0}"], ' ', $income['body']);
        $this->assertStringContainsString('600,00 MAD (2 paiement(s))', $text);
        $this->assertStringContainsString('espèces : 400,00 MAD', $text);
        $this->assertStringContainsString('chèque : 200,00 MAD', $text);
        $this->assertStringContainsString('Paiements fournisseurs sur la même période : 150,00 MAD', $text);

        $account = CashAccount::create(['ca_title' => 'Caisse principale', 'ca_code' => 'CA1', 'ca_type' => 'cash', 'ca_initial_balance' => 1000, 'ca_status' => true]);
        foreach ([['in', 500, 'active'], ['out', 200, 'active'], ['in', 9999, 'cancelled']] as $i => [$dir, $amt, $st]) {
            DB::table('cash_transactions')->insert(['ct_code' => "CT{$i}", 'cash_account_id' => $account->id, 'ct_direction' => $dir, 'ct_amount' => $amt, 'ct_date' => '2026-10-10', 'ct_label' => 'x', 'ct_status' => $st, 'created_at' => now(), 'updated_at' => now()]);
        }
        $balances = str_replace(["\u{202f}", "\u{a0}"], ' ', $this->say('solde de chaque compte de trésorerie')['body']);
        $this->assertStringContainsString('Caisse principale — 1 300,00 MAD', $balances);

        $terminal = PosTerminal::factory()->create();
        PosSession::factory()->create(['pos_terminal_id' => $terminal->id, 'user_id' => $this->admin->id, 'opened_at' => now()->subDays(2), 'closed_at' => null]);
        PosSession::factory()->create(['pos_terminal_id' => $terminal->id, 'user_id' => $this->admin->id, 'opened_at' => now()->subHours(8), 'closed_at' => now()->subHours(2), 'cash_difference' => -25, 'variance_reason' => 'monnaie rendue', 'validated_at' => null]);

        $sessions = str_replace(["\u{202f}", "\u{a0}"], ' ', $this->say('sessions de caisse')['body']);
        $this->assertStringContainsString('1 ouverte(s)', $sessions);
        $this->assertStringContainsString('Ouvertes depuis plus de 24 h', $sessions);
        $this->assertStringContainsString('à valider', $sessions);
        $this->assertStringContainsString('monnaie rendue', $sessions);
    }

    public function test_the_day_summary_and_pending_validations(): void
    {
        $this->doc('InvoiceSale', 'confirmed', '2026-10-14', 1200, 1200, '2026-10-01');
        AgentEvent::create(['type' => 'catalogue_publication', 'source' => 'orchestrator', 'status' => 'routed', 'payload' => ['text' => 'Publication de 3 fiches']]);
        AgentEvent::create(['type' => 'routine_proposition', 'source' => 'orchestrator', 'status' => 'routed', 'payload' => ['text' => 'Routine du matin']]);
        AgentEvent::create(['type' => 'catalogue_publication', 'source' => 'orchestrator', 'status' => 'done', 'payload' => ['text' => 'Déjà faite']]);

        $day = $this->say('résume la journée');
        $this->assertStringContainsString('Le point du mercredi 14 octobre 2026', $day['body']);
        $this->assertStringContainsString('1 facture(s) ou ticket(s)', $day['body']);
        $this->assertStringContainsString('Factures échues : 1', $day['body']);
        $this->assertStringContainsString('2 proposition(s)', $day['body']);
        $this->assertSame('que dois-je valider ?', $day['suggestions'][0]['text']);

        $pending = $this->say('que dois-je valider ?');
        $this->assertStringContainsString('2 proposition(s) en attente', $pending['body']);
        $this->assertStringContainsString('Publication de 3 fiches', $pending['body']);
        $this->assertStringContainsString('applique la proposition #', $pending['body']);   // pour la routine
        $this->assertStringContainsString('applique le lot #', $pending['body']);            // pour le catalogue
        $this->assertStringNotContainsString('Déjà faite', $pending['body']);
    }

    public function test_nothing_to_validate_and_existing_commands_keep_working(): void
    {
        $this->assertStringContainsString("Rien n'attend", $this->say('que dois-je valider ?')['body']);
        // Les anciennes commandes ne sont pas happées par les nouvelles lectures.
        $this->assertSame('collections', $this->say('contrôle les encaissements')['intent'] ?? 'collections');
        $this->assertStringContainsString('relances', mb_strtolower($this->say('relances à valider')['body']));
        $this->assertNotSame('business', $this->say('état des agents')['intent'] ?? '');
    }
}
