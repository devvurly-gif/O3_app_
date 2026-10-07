<?php

namespace Tests\Feature\Api;

use App\Models\AgentRoutine;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Notifications\RoutineReport;
use App\Services\Agents\RoutineRunner;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Une routine automatique prévient l'administrateur hors du chat (cloche, push si configuré) quand elle a quelque chose
 * à lui faire décider ou qu'elle a échoué — jamais quand il l'a lancée lui-même, jamais pour un compte rendu sans enjeu,
 * jamais un tiers.
 */
class RoutineNotificationTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
        Http::swap(new Factory());
        Http::fake();
        Notification::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staleQuote(): void
    {
        $client = ThirdPartner::factory()->create(['tp_title' => 'Atlas', 'tp_Role' => 'customer', 'tp_phone' => '0612345678']);
        $d = DocumentHeader::factory()->create(['document_type' => 'QuoteSale', 'status' => 'sent', 'issued_at' => '2026-09-01', 'reference' => 'DV-1', 'thirdPartner_id' => $client->id]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => 100, 'total_ttc' => 120, 'amount_paid' => 0, 'amount_due' => 0]);
    }

    private function routine(array $steps): AgentRoutine
    {
        return AgentRoutine::create(['name' => 'Matin', 'steps' => $steps, 'schedule' => ['frequency' => 'daily', 'time' => '08:00'], 'is_active' => true, 'created_by' => $this->admin->id, 'next_run_at' => now()->subMinute()]);
    }

    public function test_a_scheduled_run_with_proposals_notifies_its_creator_only(): void
    {
        $this->staleQuote();
        $other = User::factory()->admin()->create();
        $routine = $this->routine(['devis_relance']);

        app(RoutineRunner::class)->runDue();

        Notification::assertSentTo($this->admin, RoutineReport::class, function (RoutineReport $n) use ($routine) {
            $data = $n->toArray($this->admin);

            return $data['type'] === 'routine_report' && $data['routine_id'] === $routine->id && $data['pending'] >= 1 && $data['status'] === 'ok'
                && str_contains($data['title'], 'Routine « Matin » : ') && str_contains($data['title'], 'proposition(s) à valider') && in_array('database', $n->via($this->admin), true);
        });
        Notification::assertNotSentTo($other, RoutineReport::class);
        Notification::assertCount(1);
    }

    public function test_a_quiet_scheduled_run_does_not_notify(): void
    {
        $this->routine(['echues']);                                                       // lecture seule, rien à décider

        app(RoutineRunner::class)->runDue();

        Notification::assertNothingSent();
    }

    public function test_running_it_yourself_never_notifies_but_a_failed_scheduled_run_does(): void
    {
        $this->staleQuote();
        $routine = $this->routine(['devis_relance']);
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => "lance la routine #{$routine->id}"])->assertCreated();
        Notification::assertNothingSent();                                                // l'administrateur est devant l'écran

        $this->routine(['encaissements']);                                                // l'agent Recouvrement est inactif : étape non réalisée
        app(RoutineRunner::class)->runDue();

        Notification::assertSentTo($this->admin, RoutineReport::class, function (RoutineReport $n) {
            $data = $n->toArray($this->admin);

            return $data['status'] === 'error' && $data['title'] === 'Routine « Matin » : échec';          // une seule étape, non réalisée
        });
        Notification::assertCount(1);
    }
    // ── E-mail à l'administrateur ────────────────────────────────────

    private function channelsOf(RoutineReport $n): array
    {
        return $n->via($this->admin);
    }

    public function test_the_email_is_added_once_per_routine_every_six_hours_and_carries_no_figures(): void
    {
        $this->staleQuote();
        $routine = $this->routine(['encaissements']);                                  // l'agent Recouvrement est inactif : la routine échoue, donc prévient, à chaque passage
        $runner = app(RoutineRunner::class);

        $runner->run($routine);
        $runner->run($routine);                                                          // deux heures plus tard, par exemple : pas un second e-mail

        $sent = Notification::sent($this->admin, RoutineReport::class);
        $this->assertCount(2, $sent);
        $this->assertContains('mail', $this->channelsOf($sent[0]));
        $this->assertContains('database', $this->channelsOf($sent[0]));
        $this->assertNotContains('mail', $this->channelsOf($sent[1]));
        $this->assertContains('database', $this->channelsOf($sent[1]));

        $mail = $sent[0]->toMail($this->admin);
        $this->assertStringStartsWith('O3 — Routine « Matin » : ', $mail->subject);
        $text = $mail->greeting . ' ' . implode(' ', $mail->introLines) . ' ' . implode(' ', $mail->outroLines);
        $this->assertStringContainsString('Rien n\'a été appliqué', $text);
        $this->assertStringContainsString('désactive l\'e-mail des routines', $text);
        $this->assertStringStartsWith('Cordialement', (string) $mail->salutation);                  // en français, comme le reste
        $this->assertStringNotContainsString('Atlas', $text);                           // ni client ni montant dans l'e-mail
        $this->assertStringNotContainsString('DV-1', $text);

        Carbon::setTestNow(now()->addHours(7));
        $runner->run($routine);
        $this->assertContains('mail', $this->channelsOf(Notification::sent($this->admin, RoutineReport::class)->last()));
    }

    public function test_the_admin_can_switch_the_email_off_and_on_from_the_chat_and_an_invalid_address_gets_none(): void
    {
        $this->staleQuote();
        $routine = $this->routine(['devis_relance']);
        $api = $this->actingAs($this->admin, 'sanctum');

        $off = $api->postJson('/api/agents/orchestrateur', ['message' => "désactive l'e-mail des routines"])->assertCreated()->json('reply.body');
        $this->assertStringContainsString("L'e-mail d'alerte des routines est désactivé", $off);
        $this->assertSame('false', Setting::get('agents', 'routine_email'));

        app(RoutineRunner::class)->run($routine);
        $this->assertNotContains('mail', $this->channelsOf(Notification::sent($this->admin, RoutineReport::class)->first()));

        $on = $api->postJson('/api/agents/orchestrateur', ['message' => "active l'e-mail des routines"])->assertCreated()->json('reply.body');
        $this->assertStringContainsString("L'e-mail d'alerte des routines est activé", $on);
        $this->assertSame('true', Setting::get('agents', 'routine_email'));

        $this->admin->forceFill(['email' => 'pas-une-adresse'])->save();
        $this->assertNotContains('mail', (new RoutineReport(1, 'Matin', 'ok', 2, true))->via($this->admin));
    }
    public function test_the_alert_does_not_depend_on_the_cache_store(): void
    {
        config(['cache.default' => 'file']);                                              // un cache sans étiquettes : l'alerte était perdue en silence (avertissement journalisé)
        $routine = $this->routine(['encaissements']);

        app(RoutineRunner::class)->run($routine);

        Notification::assertSentTo($this->admin, RoutineReport::class, fn (RoutineReport $n) => in_array('mail', $this->channelsOf($n), true));
        $this->assertNotSame('0', Setting::get('agents', "routine_mail_at_{$routine->id}", '0'));       // le dernier e-mail est noté en base
    }
}
