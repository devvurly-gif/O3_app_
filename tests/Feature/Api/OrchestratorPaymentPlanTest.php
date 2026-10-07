<?php

namespace Tests\Feature\Api;

use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
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
 * « Propose un échéancier pour Atlas en 3 mensualités » : un calendrier et un message proposés, mémorisés au clic, aucune
 * facture ni règlement modifié, rien d'envoyé.
 */
class OrchestratorPaymentPlanTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $atlas;

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

    private function invoice(string $ref, float $due, string $status = 'confirmed'): DocumentHeader
    {
        $d = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'status' => $status, 'issued_at' => '2026-09-01', 'reference' => $ref, 'thirdPartner_id' => $this->atlas->id]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => round($due / 1.2, 2), 'total_ttc' => $due, 'amount_paid' => 0, 'amount_due' => $due]);

        return $d;
    }

    public function test_it_splits_the_whole_unpaid_balance_into_monthly_installments_with_a_message_to_send(): void
    {
        $this->invoice('FV-1', 1000);
        $this->invoice('FV-2', 2000.01);
        $this->invoice('FV-DRAFT', 9999, 'draft');

        $r = $this->say('propose un échéancier pour Atlas en 3 mensualités');

        $this->assertStringContainsString('Quincaillerie Atlas', $r['body']);
        $this->assertStringContainsString('2 facture(s) impayée(s), 3 000,01 MAD au total, en 3 versements', $r['body']);
        $this->assertStringContainsString('1/3 — 14/11/2026 — 1 000,00 MAD', $r['body']);
        $this->assertStringContainsString('2/3 — 14/12/2026 — 1 000,00 MAD', $r['body']);
        $this->assertStringContainsString('3/3 — 14/01/2027 — 1 000,01 MAD', $r['body']);          // le dernier absorbe l'arrondi
        $this->assertCount(2, $r['links']);
        $this->assertStringStartsWith('https://wa.me/212612345678?text=', $r['links'][0]['to']);
        $this->assertStringContainsString(rawurlencode('3 000,01 MAD TTC'), $r['links'][0]['to']);
        $this->assertStringStartsWith('mailto:atlas@example.com', $r['links'][1]['to']);
        $this->assertSame("Enregistrer l'échéancier", $r['suggestions'][0]['label']);
        $this->assertSame(AgentEvent::STATUS_ROUTED, AgentEvent::where('type', 'echeancier_paiement')->first()->status);
        $this->assertSame(0, AgentAction::count());
    }

    public function test_the_click_only_records_the_agreement_and_a_new_plan_replaces_the_old_one(): void
    {
        $a = $this->invoice('FV-1', 1200);

        $first = $this->say('établis un échéancier pour Atlas en 4 fois à partir du 15/11');
        $this->assertStringContainsString('1/4 — 15/11/2026 — 300,00 MAD', $first['body']);
        $done = $this->say($first['suggestions'][0]['text']);
        $this->assertStringContainsString('Échéancier enregistré pour Quincaillerie Atlas', $done['body']);
        $this->assertSame('payment_plan_recorded', AgentAction::first()->action);
        $this->assertSame(0, Payment::count());
        $this->assertSame(1200.0, (float) DocumentFooter::where('document_header_id', $a->id)->value('amount_due'));
        $this->assertNull(DocumentHeader::find($a->id)->due_at);

        $second = $this->say('propose un échéancier pour Atlas en deux mensualités');
        $this->assertStringContainsString('Un échéancier est déjà enregistré pour ce client', $second['body']);
        $this->say($second['suggestions'][0]['text']);
        $this->assertSame(1, AgentEvent::where('type', 'echeancier_paiement')->where('status', AgentEvent::STATUS_DONE)->count());
        $this->assertSame(1, AgentEvent::where('type', 'echeancier_paiement')->where('status', AgentEvent::STATUS_REJECTED)->count());
    }

    public function test_the_follow_up_list_shows_next_installment_and_current_balance(): void
    {
        $this->assertStringContainsString('Aucun échéancier enregistré', $this->say('échéanciers en cours')['body']);

        $this->invoice('FV-1', 900);
        $r = $this->say('propose un échéancier pour Atlas en 3 mensualités');
        $this->say($r['suggestions'][0]['text']);

        $list = $this->say('échéanciers en cours');
        $this->assertStringContainsString('Quincaillerie Atlas — 3 versements, 900,00 MAD à l\'origine, reste dû aujourd\'hui 900,00 MAD — prochain : 14/11/2026 (300,00 MAD)', $list['body']);
    }

    public function test_missing_information_is_asked_for_and_nothing_is_created(): void
    {
        $this->invoice('FV-1', 900);

        $this->assertStringContainsString('En combien de versements mensuels', $this->say('propose un échéancier pour Atlas')['body']);
        $this->assertStringContainsString('Pour quel client ?', $this->say('propose un échéancier en 3 mensualités')['body']);
        $this->assertStringContainsString('(de 2 à 12)', $this->say('propose un échéancier pour Atlas en 13 mensualités')['body']);
        $this->assertStringContainsString("Je n'ai pas compris la date", $this->say('propose un échéancier pour Atlas en 3 mensualités à partir du 45/13')['body']);

        ThirdPartner::factory()->create(['tp_title' => 'Bati Plus', 'tp_Role' => 'customer']);
        $this->assertStringContainsString("n'a aucune facture de vente impayée", $this->say('propose un échéancier pour Bati Plus en 3 mensualités')['body']);
        $this->assertSame(0, AgentEvent::where('type', 'echeancier_paiement')->count());
    }
}
