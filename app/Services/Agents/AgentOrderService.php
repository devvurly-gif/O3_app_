<?php

namespace App\Services\Agents;

use App\Models\AgentEvent;
use Illuminate\Support\Facades\Log;

/**
 * Donne un ordre à un agent : l'ordre devient un événement `source = manual`,
 * routé comme tout autre (règle `inventaire_demande` → agent Stocks,
 * `controle_encaissements` → agent Recouvrement), puis exécuté par l'agent.
 * L'agent prépare un brouillon, il ne modifie rien et ne contacte personne.
 */
class AgentOrderService
{
    public function __construct(
        private EventRouter $router,
        private StockInventoryAgent $stocks,
        private CollectionsAgent $collections,
    ) {
    }

    /**
     * @param array{warehouse_id?: int|null, scope?: string, note?: string|null} $order
     * @return array{ok: bool, event: AgentEvent, result?: array, message?: string, http: int}
     */
    public function orderInventory(array $order, ?string $orderedBy): array
    {
        $order = array_filter([
            'warehouse_id' => $order['warehouse_id'] ?? null,
            'scope'        => $order['scope'] ?? 'all',
            'note'         => $order['note'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $event = AgentEvent::create([
            'type'     => 'inventaire_demande',
            'source'   => 'manual',
            'status'   => AgentEvent::STATUS_NEW,
            'payload'  => [
                'order'      => $order,
                'ordered_by' => $orderedBy,
                'text'       => 'Inventaire demandé' . (isset($order['warehouse_id']) ? " (entrepôt #{$order['warehouse_id']})" : ' (tous les entrepôts)')
                    . ($order['scope'] === 'attention' ? ' — articles à vérifier seulement' : ''),
            ],
            'entities' => ['warehouse_id' => $order['warehouse_id'] ?? null],
        ]);

        return $this->run(
            $event,
            "l'agent Stocks est inactif ou aucune règle « inventaire_demande » n'est active.",
            "L'agent Stocks n'a pas pu préparer l'inventaire.",
            fn () => $this->stocks->handle($event),
        );
    }

    /**
     * Contrôle des encaissements et préparation des relances (brouillons).
     *
     * @return array{ok: bool, event: AgentEvent, result?: array, message?: string, http: int}
     */
    public function orderCollections(?string $orderedBy): array
    {
        $event = AgentEvent::create([
            'type'    => 'controle_encaissements',
            'source'  => 'manual',
            'status'  => AgentEvent::STATUS_NEW,
            'payload' => ['ordered_by' => $orderedBy, 'text' => 'Contrôle des encaissements et préparation des relances'],
        ]);

        return $this->run(
            $event,
            "l'agent Recouvrement est inactif ou aucune règle « controle_encaissements » n'est active.",
            "L'agent Recouvrement n'a pas pu contrôler les encaissements.",
            fn () => $this->collections->handle($event),
        );
    }

    /** @return array{ok: bool, event: AgentEvent, result?: array, message?: string, http: int} */
    private function run(AgentEvent $event, string $refusal, string $failure, callable $handler): array
    {
        $this->router->route($event);

        if ($event->status !== AgentEvent::STATUS_ROUTED) {
            // Un ordre que personne ne peut prendre (agent inactif, règle absente) est refusé : ce n'est
            // pas un message à classer, il ne doit donc pas rester dans « à trier ».
            $event->update([
                'status'  => AgentEvent::STATUS_REJECTED,
                'payload' => array_merge($event->payload ?? [], ['legacy_reason' => 'order_refused']),
            ]);

            return ['ok' => false, 'event' => $event, 'http' => 422, 'message' => "L'ordre n'a pas pu être confié : {$refusal}"];
        }

        $event->update(['status' => AgentEvent::STATUS_IN_PROGRESS]);

        try {
            $result = $handler();
        } catch (\Throwable $e) {
            $event->update(['status' => AgentEvent::STATUS_ERROR]);
            Log::error("Ordre d'agent non exécuté (événement {$event->id}, {$event->type}) : {$e->getMessage()}");

            return ['ok' => false, 'event' => $event, 'http' => 500, 'message' => $failure];
        }

        $event->update(['status' => AgentEvent::STATUS_DONE]);

        return ['ok' => true, 'event' => $event, 'result' => $result, 'http' => 201];
    }
}
