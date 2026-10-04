<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\AgentThreshold;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\PaymentReminder;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Services\Agents\CollectionsAgent;
use App\Services\WhatsAppService;
use Database\Seeders\AgentFoundationSeeder;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Agent Recouvrement : contrôle des encaissements et relances en brouillon,
 * validées par un humain (seul moment où un client peut être contacté).
 */
class CollectionsAgentTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $client;
    private array $sent = [];
    private bool $whatsappWorks = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
        Agent::where('domain', 'recouvrement')->update(['is_active' => true]);
        Setting::set('company', 'name', 'Jadema');

        $this->admin = User::factory()->admin()->create();
        $this->client = ThirdPartner::factory()->customer()->create(['tp_title' => 'Quincaillerie Atlas', 'tp_phone' => '0612345678']);

        $test = $this;
        $this->app->instance(WhatsAppService::class, new class($test) extends WhatsAppService {
            public function __construct(private CollectionsAgentTest $test) {}
            public function send(string $to, string $message): bool
            {
                if (!$this->test->whatsappWorks()) {
                    return false;
                }
                $this->test->record($to, $message);

                return true;
            }
        });
    }

    public function whatsappWorks(): bool
    {
        return $this->whatsappWorks;
    }

    public function record(string $to, string $message): void
    {
        $this->sent[] = [$to, $message];
    }

    private function invoice(int $daysLate, float $ttc = 1000.0, float $paid = 0.0, string $status = 'pending', ?ThirdPartner $client = null): DocumentHeader
    {
        $doc = DocumentHeader::factory()->create([
            'document_type'   => 'InvoiceSale',
            'thirdPartner_id' => ($client ?? $this->client)->id,
            'status'          => $status,
            'issued_at'       => now()->subDays($daysLate + 30),
            'due_at'          => now()->subDays($daysLate),
        ]);
        DocumentFooter::create([
            'document_header_id' => $doc->id, 'total_ht' => $ttc / 1.2, 'total_tax' => $ttc - $ttc / 1.2,
            'total_ttc' => $ttc, 'amount_paid' => $paid, 'amount_due' => $ttc - $paid,
        ]);
        if ($paid > 0) {
            $this->payment($doc, $paid);
        }

        return $doc;
    }

    private function payment(DocumentHeader $doc, float $amount): Payment
    {
        return Payment::create([
            'payment_code' => 'PAY-' . random_int(10000, 99999), 'document_header_id' => $doc->id,
            'amount' => $amount, 'method' => 'cheque', 'paid_at' => now(), 'user_id' => $this->admin->id,
        ]);
    }

    private function prepare(): array
    {
        return app(CollectionsAgent::class)->prepare();
    }

    private function as(User $u): static
    {
        return $this->actingAs($u, 'sanctum');
    }

    // ── Préparation des relances ─────────────────────────────────────

    public function test_an_overdue_invoice_gets_a_level_1_draft_and_nothing_is_sent(): void
    {
        $doc = $this->invoice(3, 1200.0);

        $r = $this->prepare();

        $this->assertSame(1, $r['overdue']);
        $reminder = PaymentReminder::sole();
        $this->assertSame(1, $reminder->level);
        $this->assertSame('draft', $reminder->status);
        $this->assertSame('whatsapp', $reminder->channel);
        $this->assertSame($doc->id, $reminder->document_header_id);
        $this->assertEquals(1200.0, (float) $reminder->amount_due);
        $this->assertSame(3, $reminder->days_overdue);
        $this->assertStringContainsString('Quincaillerie Atlas', $reminder->message);
        $this->assertStringContainsString($doc->reference, $reminder->message);
        $this->assertStringContainsString('1 200,00 MAD', $reminder->message);
        $this->assertSame([], $this->sent);
    }

    public function test_only_overdue_open_sales_invoices_are_considered(): void
    {
        $this->invoice(-10);                                  // pas encore échue
        $this->invoice(5, 500.0, 500.0, 'paid');              // payée
        $this->invoice(5, 500.0, 0.0, 'draft');               // brouillon
        $this->invoice(5, 500.0, 0.0, 'cancelled');           // annulée
        DocumentHeader::factory()->create(['document_type' => 'InvoicePurchase', 'status' => 'pending', 'due_at' => now()->subDays(9)]);

        $this->assertSame(0, $this->prepare()['overdue']);
        $this->assertSame(0, PaymentReminder::count());
    }

    public function test_a_partially_paid_invoice_is_reminded_for_the_remaining_amount_only(): void
    {
        $this->invoice(4, 1000.0, 400.0, 'partial');

        $this->prepare();

        $this->assertEquals(600.0, (float) PaymentReminder::sole()->amount_due);
        $this->assertStringContainsString('600,00 MAD', PaymentReminder::sole()->message);
    }

    public function test_running_twice_never_duplicates_a_reminder(): void
    {
        $this->invoice(3);

        $this->prepare();
        $second = $this->prepare();

        $this->assertSame(1, PaymentReminder::count());
        $this->assertSame([], $second['created']);
    }

    public function test_a_very_late_invoice_still_starts_at_level_1(): void
    {
        $this->invoice(45);

        $this->prepare();

        $this->assertSame([1], PaymentReminder::pluck('level')->all());
    }

    public function test_levels_escalate_only_after_the_previous_one_was_sent(): void
    {
        $doc = $this->invoice(45);
        $this->prepare();
        $this->prepare();   // niveau 1 toujours en brouillon : pas d'escalade
        $this->assertSame([1], PaymentReminder::pluck('level')->all());

        PaymentReminder::where('level', 1)->update(['status' => 'sent']);
        $this->prepare();
        $this->assertEqualsCanonicalizing([1, 2], PaymentReminder::pluck('level')->all());
        $this->assertStringContainsString('Malgré notre précédent rappel', PaymentReminder::where('level', 2)->value('message'));

        PaymentReminder::where('level', 2)->update(['status' => 'sent']);
        $this->prepare();
        $level3 = PaymentReminder::where('level', 3)->sole();
        $this->assertSame('manual', $level3->channel);                       // jamais de message automatique au client
        $this->assertStringContainsString('Escalade', $level3->message);
        $this->assertSame($doc->id, $level3->document_header_id);
    }

    public function test_a_rejected_reminder_is_not_recreated_nor_escalated(): void
    {
        $this->invoice(45);
        $this->prepare();
        PaymentReminder::where('level', 1)->update(['status' => 'rejected']);

        $this->prepare();

        $this->assertSame([1], PaymentReminder::pluck('level')->all());
    }

    public function test_thresholds_are_configurable(): void
    {
        $this->invoice(3);
        AgentThreshold::where('parameter', 'days_level1')->update(['value' => 5]);

        $this->assertSame([], $this->prepare()['created']);

        AgentThreshold::where('parameter', 'days_level1')->update(['value' => 2]);
        $this->assertCount(1, $this->prepare()['created']);
    }

    public function test_a_client_without_phone_gets_a_manual_reminder(): void
    {
        $this->client->update(['tp_phone' => null]);
        $this->invoice(3);

        $this->prepare();

        $this->assertSame('manual', PaymentReminder::sole()->channel);
    }

    // ── Contrôle des encaissements ───────────────────────────────────

    public function test_a_consistent_collection_has_no_anomaly(): void
    {
        $this->invoice(-5, 1000.0, 1000.0, 'paid');
        $this->invoice(-5, 800.0, 300.0, 'partial');

        $this->assertSame([], app(CollectionsAgent::class)->verify());
    }

    public function test_verification_reports_mismatches_orphans_and_wrong_statuses(): void
    {
        $paid = $this->invoice(-5, 1000.0, 1000.0, 'paid');
        $paid->payments()->delete();                                          // pied dit encaissé, aucun paiement enregistré
        $balance = $this->invoice(2, 500.0, 0.0, 'pending');
        DocumentFooter::where("document_header_id", $balance->id)->update(["amount_due" => 100]);                      // 500 − 0 ≠ 100
        $liar = $this->invoice(2, 500.0, 0.0, 'paid');                        // « payée » avec un reste dû
        $settled = $this->invoice(2, 500.0, 500.0, 'paid');                     // soldée…
        DocumentHeader::where('id', $settled->id)->update(['status' => 'pending']);    // …mais plus « payée » (l'observer de paiement la passe en payée : on force l'incohérence)
        // Paiement dont le document a été supprimé (suppression logique) : plus rattaché à rien.
        $deleted = $this->invoice(2, 500.0, 0.0, 'pending');
        $this->payment($deleted, 50);
        $deleted->delete();

        $codes = collect(app(CollectionsAgent::class)->verify())->pluck('code')->all();

        foreach (['PAID_MISMATCH', 'BALANCE_MISMATCH', 'PAID_WITH_BALANCE', 'SETTLED_NOT_PAID', 'ORPHAN_PAYMENT'] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    // ── API ──────────────────────────────────────────────────────────

    public function test_control_endpoint_runs_the_agent_and_logs_it(): void
    {
        $this->invoice(3);

        $r = $this->as($this->admin)->postJson('/api/ventes/relances/control' . 'e')->assertCreated();

        $this->assertSame(1, $r->json('created'));
        $this->assertSame(1, $r->json('overdue'));
        $event = AgentEvent::sole();
        $this->assertSame('controle_encaissements', $event->type);
        $this->assertSame('recouvrement', $event->agent->domain);
        $this->assertSame(AgentEvent::STATUS_DONE, $event->status);
        $this->assertSame('verify_collections_and_prepare_reminders', AgentAction::sole()->action);

        $index = $this->as($this->admin)->getJson('/api/ventes/relances')->assertOk();
        $this->assertSame(1, $index->json('summary.to_validate'));
        $this->assertSame(1, $index->json('last_control.created'));
        $this->assertSame('Quincaillerie Atlas', $index->json('reminders.0.client.title'));
    }

    public function test_control_is_refused_while_the_agent_is_inactive(): void
    {
        Agent::where('domain', 'recouvrement')->update(['is_active' => false]);
        $this->invoice(3);

        $this->as($this->admin)->postJson('/api/ventes/relances/controle')->assertStatus(422);

        $this->assertSame(0, PaymentReminder::count());
        $this->assertSame(AgentEvent::STATUS_REJECTED, AgentEvent::sole()->status);   // refusé, pas « à trier »
    }

    public function test_validating_a_whatsapp_reminder_sends_it_and_logs_the_decision(): void
    {
        $this->invoice(3);
        $this->prepare();
        $reminder = PaymentReminder::sole();

        $r = $this->as($this->admin)->postJson("/api/ventes/relances/{$reminder->id}/valider")->assertOk();

        $this->assertSame('sent', $r->json('status'));
        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('Quincaillerie Atlas', $this->sent[0][1]);
        $this->assertSame($this->admin->id, $reminder->fresh()->decided_by);
        $this->assertSame('reminder_validated', AgentAction::where('action', 'reminder_validated')->sole()->action);
    }

    public function test_the_human_can_edit_the_message_before_validating(): void
    {
        $this->invoice(3);
        $this->prepare();
        $reminder = PaymentReminder::sole();

        $this->as($this->admin)->putJson("/api/ventes/relances/{$reminder->id}", ['message' => 'Bonjour, petit rappel amical.'])->assertOk();
        $this->as($this->admin)->postJson("/api/ventes/relances/{$reminder->id}/valider")->assertOk();

        $this->assertSame('Bonjour, petit rappel amical.', $this->sent[0][1]);
    }

    public function test_a_failed_send_keeps_the_reminder_retryable(): void
    {
        $this->invoice(3);
        $this->prepare();
        $reminder = PaymentReminder::sole();
        $this->whatsappWorks = false;

        $r = $this->as($this->admin)->postJson("/api/ventes/relances/{$reminder->id}/valider")->assertOk();

        $this->assertSame('failed', $r->json('status'));
        $this->assertStringContainsString('WhatsApp', $r->json('error'));
        $this->assertNull($reminder->fresh()->sent_at);

        $this->whatsappWorks = true;
        $this->as($this->admin)->postJson("/api/ventes/relances/{$reminder->id}/valider")->assertOk()->assertJsonPath('status', 'sent');
    }

    public function test_a_manual_reminder_is_marked_done_without_any_message(): void
    {
        $this->invoice(45);
        $this->prepare();
        PaymentReminder::where('level', 1)->update(['status' => 'sent']);
        $this->prepare();
        PaymentReminder::where('level', 2)->update(['status' => 'sent']);
        $this->prepare();
        $level3 = PaymentReminder::where('level', 3)->sole();

        $this->as($this->admin)->postJson("/api/ventes/relances/{$level3->id}/valider")->assertOk()->assertJsonPath('status', 'sent');

        $this->assertSame([], $this->sent);
    }

    public function test_a_client_without_phone_cannot_be_reminded_by_whatsapp(): void
    {
        $this->client->update(['tp_phone' => null]);
        $this->invoice(3);
        $this->prepare();
        $reminder = PaymentReminder::sole();

        $r = $this->as($this->admin)->postJson("/api/ventes/relances/{$reminder->id}/valider", ['channel' => 'whatsapp'])->assertOk();

        $this->assertSame('failed', $r->json('status'));
        $this->assertStringContainsString('téléphone', $r->json('error'));
        $this->assertSame([], $this->sent);
    }

    public function test_rejecting_and_double_decisions(): void
    {
        $this->invoice(3);
        $this->prepare();
        $reminder = PaymentReminder::sole();

        $this->as($this->admin)->postJson("/api/ventes/relances/{$reminder->id}/rejeter", ['reason' => 'Client en négociation'])->assertOk()->assertJsonPath('status', 'rejected');
        $this->as($this->admin)->postJson("/api/ventes/relances/{$reminder->id}/valider")->assertStatus(422);
        $this->as($this->admin)->putJson("/api/ventes/relances/{$reminder->id}", ['message' => 'x'])->assertStatus(422);

        $this->assertSame([], $this->sent);
        $this->assertSame('Client en négociation', $reminder->fresh()->reason);
    }

    public function test_only_admin_and_manager_and_interactive_tokens_have_access(): void
    {
        $this->as(User::factory()->manager()->create())->getJson('/api/ventes/relances')->assertOk();
        foreach (['cashier', 'warehouse'] as $role) {
            $this->as(User::factory()->{$role}()->create())->getJson('/api/ventes/relances')->assertForbidden();
        }

        $plain = $this->admin->createToken('agent', ['achats:import'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$plain}")->getJson('/api/ventes/relances')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/ventes/relances')->assertUnauthorized();
    }
}
