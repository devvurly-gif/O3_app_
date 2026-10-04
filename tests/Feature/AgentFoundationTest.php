<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentCase;
use App\Models\AgentEvent;
use App\Models\AgentRoutingRule;
use App\Services\Agents\EventRouter;
use Database\Seeders\AgentFoundationSeeder;
use LogicException;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Socle des agents IA : routeur à règles, rattachement au dossier, file « à
 * trier » et journal d'audit en ajout seul.
 */
class AgentFoundationTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
    }

    private function event(array $attrs): AgentEvent
    {
        return AgentEvent::create($attrs + ['status' => AgentEvent::STATUS_NEW]);
    }

    public function test_pdf_invoice_is_routed_to_purchasing_and_gets_a_case(): void
    {
        $event = $this->event(['source' => 'pdf', 'payload' => ['text' => 'FACTURE N° 2026-12', 'mime' => 'application/pdf']]);

        app(EventRouter::class)->route($event);

        $event->refresh();
        $this->assertSame('facture_fournisseur', $event->type);
        $this->assertSame(AgentEvent::STATUS_ROUTED, $event->status);
        $this->assertSame('achats', $event->agent->domain);
        $this->assertNotNull($event->case_id);
    }

    public function test_event_without_matching_rule_goes_to_sort(): void
    {
        $event = $this->event(['source' => 'email', 'payload' => ['text' => 'bonjour']]);

        app(EventRouter::class)->route($event);

        $this->assertSame(AgentEvent::STATUS_TO_SORT, $event->fresh()->status);
        $this->assertNull($event->fresh()->case_id);
    }

    public function test_rule_for_inactive_agent_goes_to_sort(): void
    {
        // Phase 3 : l'agent Recouvrement est inactif au départ.
        AgentRoutingRule::where('event_type', 'virement_recu')->update(['is_active' => true]);
        $event = $this->event(['source' => 'bank', 'type' => 'virement_recu']);

        app(EventRouter::class)->route($event);

        $this->assertSame(AgentEvent::STATUS_TO_SORT, $event->fresh()->status);
    }

    public function test_higher_priority_rule_wins(): void
    {
        $event = $this->event(['source' => 'whatsapp', 'payload' => ['text' => 'où est ma commande, je veux un suivi']]);

        app(EventRouter::class)->route($event);

        $this->assertSame('suivi_colis', $event->fresh()->type);
    }

    public function test_typed_event_only_matches_rules_of_its_type(): void
    {
        $event = $this->event(['source' => 'erp', 'type' => 'alerte_stock']);

        app(EventRouter::class)->route($event);

        $event->refresh();
        $this->assertSame('alerte_stock', $event->type);
        $this->assertSame('stocks', $event->agent->domain);
    }

    public function test_typed_only_rule_never_catches_an_untyped_free_message(): void
    {
        $free = $this->event(['source' => 'whatsapp', 'payload' => ['text' => 'bonjour']]);
        $typed = $this->event(['source' => 'whatsapp', 'type' => 'commande_creee', 'payload' => ['text' => 'bonjour']]);

        app(EventRouter::class)->route($free);
        app(EventRouter::class)->route($typed);

        $this->assertSame(AgentEvent::STATUS_TO_SORT, $free->fresh()->status);
        $this->assertSame('ventes', $typed->fresh()->agent->domain);
    }

    public function test_rule_source_accepts_a_list(): void
    {
        $sms = $this->event(['source' => 'sms', 'payload' => ['text' => 'je veux un devis']]);

        app(EventRouter::class)->route($sms);

        $this->assertSame('demande_devis', $sms->fresh()->type);
    }

    public function test_events_of_same_client_share_the_open_case(): void
    {
        $a = $this->event(['source' => 'whatsapp', 'payload' => ['text' => 'devis svp'], 'entities' => ['third_partner_id' => 7]]);
        $b = $this->event(['source' => 'whatsapp', 'payload' => ['text' => 'où est ma commande'], 'entities' => ['third_partner_id' => 7]]);

        app(EventRouter::class)->route($a);
        app(EventRouter::class)->route($b);

        $this->assertSame($a->fresh()->case_id, $b->fresh()->case_id);
        $this->assertSame(1, AgentCase::count());
    }

    public function test_audit_journal_is_append_only(): void
    {
        $agent = Agent::where('domain', 'achats')->first();
        $action = AgentAction::create(['agent_id' => $agent->id, 'action' => 'import_facture', 'level' => 'auto']);

        $this->expectException(LogicException::class);
        $action->update(['level' => 'approval']);
    }

    public function test_audit_journal_rows_cannot_be_deleted(): void
    {
        $agent = Agent::where('domain', 'achats')->first();
        $action = AgentAction::create(['agent_id' => $agent->id, 'action' => 'import_facture', 'level' => 'auto']);

        $this->expectException(LogicException::class);
        $action->delete();
    }
}
