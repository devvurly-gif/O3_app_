<?php

namespace Tests\Feature\Api;

use App\Models\AgentEvent;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Services\Agents\LotClaim;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Un lot ne se traite qu'une fois, même cliqué deux fois en même temps : il est réservé par une seule instruction SQL
 * (LotClaim), sans aucun cache — un verrou de cache échoue sur un cache sans étiquettes, que la séparation des tenants exige.
 */
class OrchestratorLotLockTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $atlas;
    private DocumentHeader $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->atlas = ThirdPartner::factory()->create(['tp_title' => 'Quincaillerie Atlas', 'tp_Role' => 'customer']);
        $this->invoice = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'status' => 'confirmed', 'issued_at' => '2026-09-01', 'reference' => 'FV-1', 'thirdPartner_id' => $this->atlas->id]);
        DocumentFooter::factory()->create(['document_header_id' => $this->invoice->id, 'total_ht' => 1000, 'total_ttc' => 1200, 'amount_paid' => 0, 'amount_due' => 1200]);
    }

    private function say(string $text): string
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply.body');
    }

    private function paymentLot(): AgentEvent
    {
        $this->say('rapproche un virement de 1200 dirhams de Atlas');

        return AgentEvent::where('type', 'rapprochement_paiement')->latest('id')->firstOrFail();
    }

    private function setUpdatedAt(AgentEvent $event, string $when): void
    {
        DB::table('agent_events')->where('id', $event->id)->update(['updated_at' => $when]);
    }

    public function test_the_reservation_is_won_by_one_request_only(): void
    {
        $lot = $this->paymentLot();

        $this->assertTrue(LotClaim::take($lot->id));
        $this->assertFalse(LotClaim::take($lot->id));                                     // la seconde requête perd
        $this->assertSame(AgentEvent::STATUS_IN_PROGRESS, $lot->fresh()->status);
        $this->assertTrue(LotClaim::isBeingProcessed($lot->id));

        LotClaim::release($lot->id);
        $this->assertSame(AgentEvent::STATUS_ROUTED, $lot->fresh()->status);
        $this->assertTrue(LotClaim::take($lot->id));                                      // libéré : de nouveau prenable
    }

    public function test_a_lot_being_processed_refuses_a_concurrent_click_then_applies_exactly_once(): void
    {
        $lot = $this->paymentLot();

        $this->assertTrue(LotClaim::take($lot->id));                                      // un premier clic est en train de traiter ce lot
        $this->assertStringContainsString("Le lot #{$lot->id} est déjà en cours de traitement", $this->say("applique le lot #{$lot->id}"));
        $this->assertSame(0, Payment::count());
        $this->assertSame(AgentEvent::STATUS_IN_PROGRESS, $lot->fresh()->status);         // le refus ne touche pas à la réservation du premier clic

        $this->setUpdatedAt($lot, now()->subMinutes(10)->toDateTimeString());              // une réservation abandonnée (processus tué) se récupère au bout de 5 minutes
        $this->assertFalse(LotClaim::isBeingProcessed($lot->id));
        $this->assertStringContainsString('Paiement enregistré', $this->say("applique le lot #{$lot->id}"));
        $this->assertStringContainsString('déjà été traité', $this->say("applique le lot #{$lot->id}"));
        $this->assertSame(1, Payment::count());
        $this->assertSame(AgentEvent::STATUS_DONE, $lot->fresh()->status);
    }

    public function test_a_lot_the_application_refuses_goes_back_to_waiting(): void
    {
        $lot = $this->paymentLot();
        Payment::create(['document_header_id' => $this->invoice->id, 'amount' => 1200, 'method' => 'cash', 'paid_at' => now(), 'user_id' => $this->admin->id]);   // réglée entre-temps

        $this->assertStringContainsString('a changé depuis la proposition', $this->say("applique le lot #{$lot->id}"));

        $this->assertSame(AgentEvent::STATUS_ROUTED, $lot->fresh()->status);             // refusé sans rien consommer : il reste à traiter ou à ignorer
        $this->assertStringContainsString('est ignoré', $this->say("ignore le lot #{$lot->id}"));
    }

    public function test_the_agent_studio_proposals_use_the_same_reservation(): void
    {
        $event = AgentEvent::create(['type' => 'consigne_proposition', 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_ROUTED, 'payload' => ['text' => 'Consigne']]);

        $this->assertTrue(LotClaim::take($event->id));
        $this->assertStringContainsString("La proposition #{$event->id} est déjà en cours de traitement", $this->say("applique la proposition #{$event->id}"));
        $this->assertSame(AgentEvent::STATUS_IN_PROGRESS, $event->fresh()->status);
    }

    public function test_it_does_not_depend_on_the_cache_store(): void
    {
        config(['cache.default' => 'file']);                                              // un cache sans étiquettes : c'était le cas qui faisait échouer le verrou de cache
        $lot = $this->paymentLot();

        $this->assertStringContainsString('Paiement enregistré', $this->say("applique le lot #{$lot->id}"));
        $this->assertSame(1, Payment::count());
    }
    public function test_a_payment_lot_never_offers_the_catalogue_next_step_and_says_nothing_about_product_sheets(): void
    {
        \App\Models\Product::factory()->count(2)->create(['p_status' => true]);          // des fiches à compléter : le parcours du catalogue aurait une « étape suivante » à proposer
        $api = $this->actingAs($this->admin, 'sanctum');
        $other = $this->paymentLot();                                                      // deux lots proposés pour la même facture : l'un sera appliqué, l'autre ignoré
        $lot = $this->paymentLot();

        $applied = $api->postJson('/api/agents/orchestrateur', ['message' => "applique le lot #{$lot->id}"])->assertCreated()->json('reply');
        $this->assertStringContainsString('Paiement enregistré', $applied['body']);
        $this->assertNotContains('Étape suivante', array_column($applied['suggestions'] ?? [], 'label'));

        $ignored = $api->postJson('/api/agents/orchestrateur', ['message' => "ignore le lot #{$other->id}"])->assertCreated()->json('reply');
        $this->assertStringContainsString("rien n'a été enregistré ni modifié", $ignored['body']);
        $this->assertStringNotContainsString('fiche', $ignored['body']);
        $this->assertNotContains('Étape suivante', array_column($ignored['suggestions'] ?? [], 'label'));
    }
}
