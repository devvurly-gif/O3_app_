<?php

namespace Tests\Feature\Api;

use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Relance les devis sans réponse » : des messages préparés et des liens WhatsApp / e-mail, jamais d'envoi par O3,
 * aucun devis modifié, et pas deux fois la même relance en sept jours.
 */
class OrchestratorQuoteFollowUpTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

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

    private function quote(string $ref, string $status, string $day, float $ttc, array $client): DocumentHeader
    {
        $d = DocumentHeader::factory()->create(['document_type' => 'QuoteSale', 'status' => $status, 'issued_at' => $day, 'reference' => $ref, 'thirdPartner_id' => ThirdPartner::factory()->create($client)->id]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => round($ttc / 1.2, 2), 'total_ttc' => $ttc, 'amount_paid' => 0, 'amount_due' => 0]);

        return $d;
    }

    public function test_it_prepares_messages_and_links_for_old_open_quotes_only(): void
    {
        $old = $this->quote('DV-OLD', 'sent', '2026-09-20', 1200, ['tp_title' => 'Quincaillerie Atlas', 'tp_phone' => '0612345678', 'tp_email' => 'atlas@example.com']);
        $mail = $this->quote('DV-MAIL', 'pending', '2026-09-25', 600, ['tp_title' => 'Bâti Plus', 'tp_phone' => null, 'tp_email' => 'bati@example.com']);
        $none = $this->quote('DV-NONE', 'confirmed', '2026-09-26', 300, ['tp_title' => 'Sans Contact', 'tp_phone' => null, 'tp_email' => null]);
        $this->quote('DV-RECENT', 'sent', '2026-10-12', 800, ['tp_title' => 'Récent', 'tp_phone' => '0600000000']);
        $this->quote('DV-DONE', 'converted', '2026-09-01', 800, ['tp_title' => 'Déjà converti', 'tp_phone' => '0600000001']);

        $r = $this->say('relance les devis sans réponse');

        $this->assertStringContainsString('3 devis ouvert(s) depuis plus de 10 jour(s)', $r['body']);
        $this->assertStringContainsString('DV-OLD', $r['body']);
        $this->assertStringContainsString('(pas de téléphone : e-mail)', $r['body']);
        $this->assertStringContainsString('(ni téléphone ni e-mail', $r['body']);
        $this->assertStringContainsString("O3 n'envoie rien", $r['body']);
        $this->assertStringNotContainsString('DV-RECENT', $r['body']);
        $this->assertStringNotContainsString('DV-DONE', $r['body']);

        $this->assertCount(2, $r['links']);
        $this->assertStringStartsWith('https://wa.me/212612345678?text=', $r['links'][0]['to']);
        $this->assertStringContainsString(rawurlencode('devis DV-OLD du 20/09/2026'), $r['links'][0]['to']);
        $this->assertStringContainsString(rawurlencode('1 200,00 MAD TTC'), $r['links'][0]['to']);
        $this->assertStringContainsString(rawurlencode('Atlas Négoce'), $r['links'][0]['to']);
        $this->assertStringStartsWith('mailto:bati@example.com?subject=', $r['links'][1]['to']);

        $event = AgentEvent::where('type', 'relance_devis')->firstOrFail();
        $this->assertSame(AgentEvent::STATUS_ROUTED, $event->status);
        $this->assertSame('Marquer ces devis comme relancés', $r['suggestions'][0]['label']);
        $this->assertSame(['sent', 'pending', 'confirmed'], [$old->fresh()->status, $mail->fresh()->status, $none->fresh()->status]);
        $this->assertSame(0, AgentAction::count());
    }

    public function test_marking_as_done_writes_the_journal_and_avoids_proposing_the_same_quotes_again(): void
    {
        $q = $this->quote('DV-OLD', 'sent', '2026-09-20', 1200, ['tp_title' => 'Atlas', 'tp_phone' => '0612345678']);
        $r = $this->say('relance les devis sans réponse');

        $done = $this->say($r['suggestions'][0]['text']);
        $this->assertStringContainsString('inscrite au journal : DV-OLD', $done['body']);
        $this->assertStringContainsString("Aucun devis n'a été modifié", $done['body']);
        $this->assertSame(AgentEvent::STATUS_DONE, AgentEvent::where('type', 'relance_devis')->first()->status);
        $this->assertSame('quote_followup_recorded', AgentAction::first()->action);
        $this->assertSame('sent', $q->fresh()->status);

        $again = $this->say('prépare les relances de devis');
        $this->assertStringContainsString('Aucun devis à relancer', $again['body']);

        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'UTC'));                  // huit jours plus tard
        AgentEvent::query()->update(['created_at' => '2026-10-14 10:00:00']);
        $later = $this->say('relance les devis sans réponse');
        $this->assertStringContainsString('DV-OLD', $later['body']);
    }

    public function test_ignoring_changes_nothing_and_the_threshold_can_be_given(): void
    {
        $this->quote('DV-A', 'sent', '2026-10-06', 500, ['tp_title' => 'Huit jours', 'tp_phone' => '+212 6 12 34 56 78']);

        $this->assertStringContainsString('Aucun devis à relancer', $this->say('relance les devis sans réponse')['body']);

        $r = $this->say('relance les devis sans réponse depuis 5 jours');
        $this->assertStringContainsString('DV-A', $r['body']);
        $this->assertStringStartsWith('https://wa.me/212612345678?', $r['links'][0]['to']);

        $this->say($r['suggestions'][1]['text']);
        $this->assertSame(AgentEvent::STATUS_DONE === AgentEvent::where('type', 'relance_devis')->first()->status, false);
        $this->assertSame(0, AgentAction::where('action', 'quote_followup_recorded')->count());
    }
}
