<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Services\Agents\RoutineSteps;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Relance les versements en retard » : un versement échu non couvert par les règlements reçus depuis l'échéancier est
 * repéré ; les messages sont préparés, jamais envoyés ; un client déjà relancé n'est pas reproposé avant 7 jours.
 */
class OrchestratorLateInstallmentsTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $atlas;
    private DocumentHeader $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Setting::set('company', 'name', 'Atlas Négoce');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->atlas = ThirdPartner::factory()->create(['tp_title' => 'Quincaillerie Atlas', 'tp_Role' => 'customer', 'tp_phone' => '0612345678', 'tp_email' => 'atlas@example.com']);
        $this->invoice = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'status' => 'confirmed', 'issued_at' => '2026-08-20', 'reference' => 'FV-1', 'thirdPartner_id' => $this->atlas->id]);
        DocumentFooter::factory()->create(['document_header_id' => $this->invoice->id, 'total_ht' => 750, 'total_ttc' => 900, 'amount_paid' => 0, 'amount_due' => 900]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function say(string $text): array
    {
        $r = $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
        Http::assertNothingSent();

        return $r;
    }

    private function plan(array $dates = ['2026-09-14', '2026-10-01', '2026-11-01']): AgentEvent
    {
        $installments = [];
        foreach ($dates as $i => $date) {
            $installments[] = ['n' => $i + 1, 'date' => $date, 'amount' => 300.0];
        }

        return AgentEvent::create([
            'type' => 'echeancier_paiement', 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_DONE, 'agent_id' => Agent::where('domain', 'recouvrement')->value('id'),
            'payload' => ['client_id' => $this->atlas->id, 'client' => 'Quincaillerie Atlas', 'total' => 900.0, 'installments' => $installments, 'recorded_at' => '2026-09-01 10:00:00'],
        ]);
    }

    private function pay(float $amount, string $day): void
    {
        Payment::create(['document_header_id' => $this->invoice->id, 'amount' => $amount, 'method' => 'bank_transfer', 'paid_at' => $day, 'user_id' => $this->admin->id]);
    }

    public function test_it_lists_the_oldest_uncovered_installment_without_proposing_anything(): void
    {
        $this->plan();

        $r = $this->say('versements en retard');

        $this->assertStringContainsString('1 client(s) avec un versement en retard', $r['body']);
        $this->assertStringContainsString('Quincaillerie Atlas — versement 1/3 du 14/09/2026 — 30 jour(s) de retard — 600,00 MAD à régler', $r['body']);   // 14/09 et 01/10 échus, rien reçu
        $this->assertSame('Préparer les relances', $r['suggestions'][0]['label']);
        $this->assertSame(0, AgentEvent::where('type', 'relance_versement')->count());
    }

    public function test_a_payment_after_the_plan_covers_the_first_installment_only(): void
    {
        $this->plan();
        $this->pay(300, '2026-09-20');

        $r = $this->say('versements en retard');

        $this->assertStringContainsString('versement 2/3 du 01/10/2026 — 13 jour(s) de retard — 300,00 MAD à régler', $r['body']);

        $this->pay(300, '2026-10-05');
        $this->assertStringContainsString('Aucun versement en retard', $this->say('versements en retard')['body']);
    }

    public function test_a_payment_before_the_plan_or_installments_not_yet_due_do_not_count(): void
    {
        $this->plan(['2026-10-14', '2026-11-14']);                                                   // le premier tombe aujourd'hui : pas encore en retard
        $this->assertStringContainsString('Aucun versement en retard', $this->say('versements en retard')['body']);

        AgentEvent::query()->delete();
        $this->plan(['2026-09-14', '2026-10-14']);
        $this->pay(300, '2026-08-25');                                                               // avant l'enregistrement de l'échéancier : ne couvre rien
        $this->assertStringContainsString('versement 1/2 du 14/09/2026', $this->say('versements en retard')['body']);
    }

    public function test_the_reminder_prepares_messages_and_links_and_the_click_only_writes_the_journal(): void
    {
        $this->plan();

        $r = $this->say('relance les versements en retard');

        $this->assertStringContainsString('1 client(s) avec un versement en retard (lot #', $r['body']);
        $this->assertCount(1, $r['links']);                                                          // WhatsApp de préférence, e-mail à défaut
        $this->assertStringStartsWith('https://wa.me/212612345678?text=', $r['links'][0]['to']);
        $this->assertStringContainsString(rawurlencode('600,00 MAD'), $r['links'][0]['to']);
        $this->assertStringContainsString(rawurlencode('Atlas Négoce'), $r['links'][0]['to']);
        $this->assertSame(AgentEvent::STATUS_ROUTED, AgentEvent::where('type', 'relance_versement')->first()->status);
        $this->assertSame(0, AgentAction::count());

        $done = $this->say($r['suggestions'][0]['text']);
        $this->assertStringContainsString('inscrite au journal : Quincaillerie Atlas', $done['body']);
        $this->assertSame('installment_reminder_recorded', AgentAction::first()->action);
        $this->assertSame(0, Payment::count());
        $this->assertSame(900.0, (float) DocumentFooter::where('document_header_id', $this->invoice->id)->value('amount_due'));

        $again = $this->say('relance les versements en retard');
        $this->assertStringContainsString('déjà été relancés ces 7 derniers jours', $again['body']);
    }

    public function test_it_is_a_routine_step_and_the_quote_and_plan_commands_keep_their_meaning(): void
    {
        $this->assertSame('relance les versements en retard', RoutineSteps::KNOWN['versements_retard']['phrase']);
        $this->assertContains('versements_retard', RoutineSteps::sanitize(['versements_retard']));

        $this->plan();
        $this->assertStringContainsString('Échéancier proposé', $this->say('propose un échéancier pour Atlas en 3 mensualités')['body']);
        $this->assertSame(0, AgentEvent::where('type', 'relance_versement')->count());
        $this->assertSame(0, AgentEvent::where('type', 'relance_devis')->count());
    }
}
