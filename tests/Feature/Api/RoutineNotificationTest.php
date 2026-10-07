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
}
