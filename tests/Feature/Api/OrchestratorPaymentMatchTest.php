<?php

namespace Tests\Feature\Api;

use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\ThirdPartner;
use App\Models\User;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Rapproche un virement de 4 500 dirhams de Atlas » : une affectation proposée, jamais enregistrée avant le clic, et
 * jamais de message au client.
 */
class OrchestratorPaymentMatchTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $atlas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->atlas = ThirdPartner::factory()->create(['tp_title' => 'Quincaillerie Atlas', 'tp_Role' => 'customer']);
    }

    private function say(string $text): array
    {
        $r = $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
        Http::assertNothingSent();

        return $r;
    }

    private function invoice(string $ref, string $day, float $due, ?ThirdPartner $tp = null, float $total = null): DocumentHeader
    {
        $d = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'status' => 'confirmed', 'issued_at' => $day, 'reference' => $ref, 'thirdPartner_id' => ($tp ?? $this->atlas)->id]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => round(($total ?? $due) / 1.2, 2), 'total_ttc' => $total ?? $due, 'amount_paid' => ($total ?? $due) - $due, 'amount_due' => $due]);

        return $d;
    }

    public function test_a_named_client_gets_the_oldest_invoices_settled_first_and_nothing_is_recorded_yet(): void
    {
        $this->invoice('FV-1', '2026-09-01', 1000);
        $this->invoice('FV-2', '2026-09-10', 2000);
        $this->invoice('FV-3', '2026-09-20', 5000);

        $r = $this->say('rapproche un virement de 4 500,50 dirhams de Quincaillerie Atlas réf VIR2026');

        $this->assertStringContainsString('Virement de 4 500,50 MAD (réf. VIR2026)', $r['body']);
        $this->assertStringContainsString('FV-1 — Quincaillerie Atlas — reste 1 000,00 MAD → affecté 1 000,00 MAD (soldée)', $r['body']);
        $this->assertStringContainsString('FV-2 — Quincaillerie Atlas — reste 2 000,00 MAD → affecté 2 000,00 MAD (soldée)', $r['body']);
        $this->assertStringContainsString('FV-3 — Quincaillerie Atlas — reste 5 000,00 MAD → affecté 1 500,50 MAD (partiel)', $r['body']);
        $this->assertStringNotContainsString('Trop-perçu', $r['body']);
        $this->assertSame('Enregistrer le paiement', $r['suggestions'][0]['label']);
        $this->assertSame(0, Payment::count());
        $this->assertSame(AgentEvent::STATUS_ROUTED, AgentEvent::where('type', 'rapprochement_paiement')->first()->status);
    }

    public function test_the_click_records_the_payments_updates_the_balances_and_notifies_nobody(): void
    {
        $this->mock(\App\Services\PaymentNotificationService::class)->shouldNotReceive('send');         // le client ne reçoit rien
        $a = $this->invoice('FV-1', '2026-09-01', 1000);
        $b = $this->invoice('FV-2', '2026-09-10', 2000);

        $r = $this->say('rapproche un virement de 1 500 dirhams de Atlas');
        $done = $this->say($r['suggestions'][0]['text']);

        $this->assertStringContainsString('Paiement enregistré', $done['body']);
        $this->assertSame(2, Payment::count());
        $p = Payment::where('document_header_id', $a->id)->firstOrFail();
        $this->assertSame('bank_transfer', $p->method);
        $this->assertSame(1000.0, (float) $p->amount);
        $this->assertSame($this->admin->id, $p->user_id);
        $this->assertSame(0.0, (float) DocumentFooter::where('document_header_id', $a->id)->value('amount_due'));
        $this->assertSame(1500.0, (float) DocumentFooter::where('document_header_id', $b->id)->value('amount_due'));
        $this->assertSame('payment_matched', AgentAction::first()->action);
        $this->assertFalse(Payment::$skipNotification);                                       // rétabli après coup
    }

    public function test_surplus_is_reported_and_not_recorded_and_changed_invoices_refuse_the_batch(): void
    {
        $a = $this->invoice('FV-1', '2026-09-01', 800);

        $r = $this->say('rapproche un chèque de 1000 dh de Atlas');
        $this->assertStringContainsString('Trop-perçu non affecté : 200,00 MAD', $r['body']);

        Payment::create(['document_header_id' => $a->id, 'amount' => 800, 'method' => 'cash', 'paid_at' => now(), 'user_id' => $this->admin->id]);      // réglée entre-temps
        $done = $this->say($r['suggestions'][0]['text']);
        $this->assertStringContainsString('a changé depuis la proposition', $done['body']);
        $this->assertSame(1, Payment::count());
    }

    public function test_without_a_client_only_an_exact_unique_amount_is_proposed(): void
    {
        $other = ThirdPartner::factory()->create(['tp_title' => 'Bati Plus', 'tp_Role' => 'customer']);
        $this->invoice('FV-1', '2026-09-01', 750);
        $this->invoice('FV-2', '2026-09-02', 1250, $other);
        $this->invoice('FV-3', '2026-09-03', 1250);

        $one = $this->say('j\'ai reçu un virement de 750 dirhams');
        $this->assertStringContainsString('FV-1', $one['body']);
        $this->assertSame('Enregistrer le paiement', $one['suggestions'][0]['label']);

        $many = $this->say('rapproche un virement de 1250 dirhams');
        $this->assertStringContainsString('2 factures ont un reste à payer de 1 250,00 MAD', $many['body']);
        $this->assertStringNotContainsString('affectation proposée', $many['body']);

        $none = $this->say('rapproche un chèque de 999 dirhams');
        $this->assertStringContainsString("Aucune facture de vente n'a un reste à payer de 999,00 MAD", $none['body']);
        $this->assertSame(0, Payment::count());
    }

    public function test_the_method_is_required_and_reads_keep_their_meaning(): void
    {
        $this->invoice('FV-1', '2026-09-01', 750);

        $this->assertStringContainsString('Quel mode de paiement ?', $this->say('rapproche un paiement de 750 dirhams')['body']);
        $this->assertSame(0, AgentEvent::where('type', 'rapprochement_paiement')->count());
        $this->assertStringNotContainsString('affectation proposée', $this->say('chèques reçus ce mois')['body']);
    }
}
