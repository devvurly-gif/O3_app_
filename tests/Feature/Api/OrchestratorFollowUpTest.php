<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\User;
use App\Services\Agents\FollowUp;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Les questions de suite (« et hier ? », « et par vendeur ? ») reprennent la dernière lecture en n'en changeant que la
 * période ou le découpage ; sans contexte, ou avec une lecture qui ne dépend pas d'une période, elles ne sont pas devinées.
 */
class OrchestratorFollowUpTest extends TestCase
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
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function say(string $text): string
    {
        $body = $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply.body');
        Http::assertNothingSent();

        return $body;
    }

    public function test_the_follow_up_resolver_changes_only_the_period_or_the_grouping(): void
    {
        $this->assertSame('ventes par vendeur hier', FollowUp::resolve('et hier ?', 'ventes du mois par vendeur'));
        $this->assertSame('ventes du mois dernier', FollowUp::resolve('et le mois dernier', 'ventes d hier'));
        $this->assertSame('ventes cette semaine', FollowUp::resolve('cette semaine', 'ventes du mois'));
        $this->assertSame('ventes de la semaine derniere', FollowUp::resolve('et la semaine derniere', 'ventes du mois'));
        $this->assertSame('top 10 des produits vendus 15 derniers jours', FollowUp::resolve('et les 15 derniers jours ?', 'top 10 des produits vendus ce mois'));
        $this->assertSame('ventes du mois par vendeur', FollowUp::resolve('et par vendeur ?', 'ventes du mois'));
        $this->assertSame('ventes du mois par caisse', FollowUp::resolve('par caisse', 'chiffre d affaires du mois'));
        $this->assertSame('meilleurs clients du mois', FollowUp::resolve('et par client', 'ventes du mois'));
        $this->assertSame('achats du mois par fournisseur', FollowUp::resolve('et par fournisseur', 'achats du mois'));

        // Pas une suite : phrase longue, mot inconnu, ou lecture qui ne dépend pas d'une période.
        $this->assertNull(FollowUp::resolve('et hier ?', 'factures echues'));
        $this->assertNull(FollowUp::resolve('et alors ?', 'ventes du mois'));
        $this->assertNull(FollowUp::resolve('et hier je suis allé au marché avec mon frère', 'ventes du mois'));
        $this->assertNull(FollowUp::resolve('stock faible', 'ventes du mois'));
    }

    public function test_a_follow_up_in_the_chat_reuses_the_previous_question(): void
    {
        $d = \App\Models\DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'status' => 'confirmed', 'issued_at' => '2026-10-13', 'user_id' => $this->admin->id]);
        \App\Models\DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => 500, 'total_ttc' => 600]);

        $this->assertStringContainsString('Ventes du mois', $this->say('ventes du mois par vendeur'));   // le découpage « par vendeur » prime déjà
        $yesterday = $this->say('et hier ?');
        $this->assertStringStartsWith('Suite de votre question : « ventes par vendeur hier ».', $yesterday);
        $this->assertStringContainsString("Ventes d'hier par utilisateur", $yesterday);

        $this->say('ventes du mois');
        $this->assertStringContainsString('du mois dernier', $this->say('et le mois dernier ?'));
        $this->assertStringContainsString('de la semaine dernière', $this->say('et la semaine dernière'));      // la suite d'une suite : on garde la dernière lecture
        $this->say('ventes du mois');
        $this->assertStringContainsString('Meilleurs clients', $this->say('et par client ?'));
    }

    public function test_without_context_or_with_an_unrelated_read_it_does_not_guess(): void
    {
        $this->assertStringContainsString("Je n'ai pas compris", $this->say('et hier ?'));                      // aucune question précédente

        $this->say('factures échues');
        $this->assertStringContainsString("Je n'ai pas compris", $this->say('et hier ?'));                      // « factures échues » ne dépend pas d'une période

        $this->say('ventes du mois');
        Carbon::setTestNow(now()->addMinutes(31));
        $this->assertStringContainsString("Je n'ai pas compris", $this->say('et hier ?'));                      // le contexte a expiré

        // Une phrase comprise telle quelle n'est jamais prise pour une suite.
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
        $this->say('ventes du mois');
        $this->assertStringContainsString("Aucun produit n'est au seuil d'alerte", $this->say('stock faible'));
    }
}
