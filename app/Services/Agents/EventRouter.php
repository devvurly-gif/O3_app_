<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentCase;
use App\Models\AgentEvent;
use App\Models\AgentRoutingRule;

/**
 * Routeur à règles des agents IA (sans LLM) : la première règle active qui
 * correspond, par priorité décroissante, désigne le type d'événement et
 * l'agent cible. Sans règle (ou sans agent actif pour le domaine visé),
 * l'événement passe à `to_sort` pour classement humain.
 *
 * Conditions d'une règle (toutes facultatives, toutes doivent tenir) :
 *   source   : AgentEvent::source égale à cette valeur, ou figure dans cette liste
 *   keywords : au moins un des mots figure dans payload.text (insensible à la casse)
 *   mime     : égalité avec payload.mime
 *   typed_only : la règle ne s'applique qu'à un événement déjà typé par son émetteur
 * Un événement déjà typé (ex. alerte ERP) n'est comparé qu'aux règles du même type.
 */
class EventRouter
{
    public function route(AgentEvent $event): AgentEvent
    {
        $rule = $this->matchingRule($event);
        $agent = $rule
            ? Agent::where('domain', $rule->agent_domain)->where('is_active', true)->first()
            : null;

        if (!$rule || !$agent) {
            $event->update(['status' => AgentEvent::STATUS_TO_SORT]);

            return $event;
        }

        $event->update([
            'type'     => $rule->event_type,
            'agent_id' => $agent->id,
            'case_id'  => $event->case_id ?? $this->attachToCase($event)->id,
            'priority' => $rule->priority,
            'status'   => AgentEvent::STATUS_ROUTED,
        ]);

        return $event;
    }

    private function matchingRule(AgentEvent $event): ?AgentRoutingRule
    {
        return AgentRoutingRule::query()
            ->where('is_active', true)
            ->when($event->type, fn ($q) => $q->where('event_type', $event->type))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->first(fn (AgentRoutingRule $rule) => $this->matches($rule, $event));
    }

    private function matches(AgentRoutingRule $rule, AgentEvent $event): bool
    {
        $c = $rule->conditions ?? [];
        $payload = $event->payload ?? [];

        // Règle réservée aux événements déjà typés par leur émetteur : elle ne
        // doit jamais « attraper » un message libre qui n'a que la bonne source.
        if (!empty($c['typed_only']) && $event->type === null) {
            return false;
        }
        if (isset($c['source']) && !in_array($event->source, (array) $c['source'], true)) {
            return false;
        }
        if (isset($c['mime']) && $c['mime'] !== ($payload['mime'] ?? null)) {
            return false;
        }
        if (!empty($c['keywords'])) {
            $text = mb_strtolower((string) ($payload['text'] ?? ''));
            $hit = collect($c['keywords'])->contains(fn ($k) => $text !== '' && str_contains($text, mb_strtolower($k)));
            if (!$hit) {
                return false;
            }
        }

        return true;
    }

    /** Dossier ouvert du client si connu, sinon nouveau dossier. */
    private function attachToCase(AgentEvent $event): AgentCase
    {
        $partnerId = $event->entities['third_partner_id'] ?? null;

        if ($partnerId) {
            $open = AgentCase::where('third_partner_id', $partnerId)->where('status', 'open')->latest('id')->first();
            if ($open) {
                return $open;
            }
        }

        return AgentCase::create([
            'third_partner_id' => $partnerId,
            'status'           => 'open',
            'opened_at'        => now(),
        ]);
    }
}
