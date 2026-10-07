<?php

namespace Tests\Feature\Api;

use App\Models\AgentEvent;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\ThirdPartner;
use App\Models\User;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Un lot ne se traite qu'une fois, même cliqué deux fois en même temps : sans verrou, deux requêtes qui lisent « en
 * attente » ensemble créeraient deux fois les mêmes brouillons ou règlements.
 */
class OrchestratorLotLockTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    private function say(string $text): string
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply.body');
    }

    public function test_a_lot_being_processed_refuses_a_concurrent_click_then_applies_exactly_once(): void
    {
        $atlas = ThirdPartner::factory()->create(['tp_title' => 'Quincaillerie Atlas', 'tp_Role' => 'customer']);
        $invoice = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'status' => 'confirmed', 'issued_at' => '2026-09-01', 'reference' => 'FV-1', 'thirdPartner_id' => $atlas->id]);
        DocumentFooter::factory()->create(['document_header_id' => $invoice->id, 'total_ht' => 1000, 'total_ttc' => 1200, 'amount_paid' => 0, 'amount_due' => 1200]);

        $this->say('rapproche un virement de 1200 dirhams de Atlas');
        $lot = AgentEvent::where('type', 'rapprochement_paiement')->firstOrFail();

        $lock = Cache::lock("agent-lot:{$lot->id}", 60);                                  // un premier clic est en train de traiter ce lot
        $this->assertTrue($lock->get());
        $this->assertStringContainsString("Le lot #{$lot->id} est déjà en cours de traitement", $this->say("applique le lot #{$lot->id}"));
        $this->assertSame(0, Payment::count());
        $this->assertSame(AgentEvent::STATUS_ROUTED, $lot->fresh()->status);               // refusé sans rien consommer : le lot reste à valider
        $lock->release();

        $this->assertStringContainsString('Paiement enregistré', $this->say("applique le lot #{$lot->id}"));
        $this->assertStringContainsString('déjà été traité', $this->say("applique le lot #{$lot->id}"));
        $this->assertSame(1, Payment::count());
        $this->assertTrue(Cache::lock("agent-lot:{$lot->id}", 5)->get());                 // le verrou est bien libéré après traitement
    }

    public function test_the_agent_studio_proposals_use_the_same_lock(): void
    {
        $event = AgentEvent::create(['type' => 'consigne_proposition', 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_ROUTED, 'payload' => ['text' => 'Consigne']]);

        $lock = Cache::lock("agent-lot:{$event->id}", 60);
        $this->assertTrue($lock->get());
        $this->assertStringContainsString("La proposition #{$event->id} est déjà en cours de traitement", $this->say("applique la proposition #{$event->id}"));
        $this->assertSame(AgentEvent::STATUS_ROUTED, $event->fresh()->status);
        $lock->release();
    }
}
