<?php

namespace App\Services\Agents;

use App\Models\AgentEvent;
use App\Models\OrderMessage;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Pont entre la messagerie commandes et le routeur des agents IA : chaque
 * message entrant WhatsApp ou SMS devient un AgentEvent routé par EventRouter.
 *
 * Mode observation : InboundOrderService continue de traiter la commande
 * lui-même (c'est lui qui crée le BL brouillon) ; l'événement, son agent cible
 * et son dossier sont seulement enregistrés, puis l'événement est clos si le
 * routeur l'a attribué. Rien ne change pour l'expéditeur. Les messages refusés
 * ou d'expéditeur inconnu ne sont pas routés (voir outcome()).
 *
 * Désactivé par défaut : Setting agents / router_enabled = 'true' pour l'activer.
 * Ne doit jamais faire échouer le traitement d'un message : toute erreur est
 * journalisée et ignorée.
 */
class OrderMessageEventRecorder
{
    private const CHANNELS = ['whatsapp', 'sms'];

    public function __construct(private EventRouter $router)
    {
    }

    public function record(OrderMessage $message): ?AgentEvent
    {
        if ($message->direction !== 'in' || !in_array($message->channel, self::CHANNELS, true)) {
            return null;
        }
        if (Setting::get('agents', 'router_enabled', 'false') !== 'true') {
            return null;
        }

        try {
            if (AgentEvent::where('order_message_id', $message->id)->exists()) {
                return null;
            }

            $reason = $message->meta['reason'] ?? null;
            $outcome = $this->outcome($message, $reason);

            $event = AgentEvent::create([
                'source'           => $message->channel,
                'order_message_id' => $message->id,
                'type'             => $outcome['type'],
                'status'           => $outcome['status'],
                // Le corps est déjà expurgé du PIN par InboundOrderService.
                'payload'          => array_filter([
                    'text'          => $message->body,
                    'channel'       => $message->channel,
                    'legacy_status' => $message->status,
                    'legacy_reason' => $reason,
                ]),
                'entities'         => array_filter([
                    'third_partner_id' => $message->third_partner_id,
                    'phone'            => $message->phone,
                    'document_id'      => $message->document_id,
                ]),
            ]);

            if ($outcome['route']) {
                $this->router->route($event);

                if ($event->status === AgentEvent::STATUS_ROUTED) {
                    $event->update(['status' => AgentEvent::STATUS_DONE]);
                }
            }

            return $event;
        } catch (\Throwable $e) {
            Log::warning("Agents : événement non enregistré pour le message {$message->id} : {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Ce que la messagerie a déjà décidé commande le sort de l'événement :
     *  - refusé en amont (PIN, débit, canal coupé) : rejected, jamais routé, aucun dossier ;
     *  - expéditeur inconnu ou ambigu : to_sort, jamais routé, aucun dossier (un numéro
     *    quelconque ne doit pas pouvoir ouvrir de dossier) ;
     *  - message qui a produit un BL : typé commande_creee, donc routé sans mot-clé ;
     *  - le reste : routé par les règles (mots-clés, source).
     *
     * @return array{type: ?string, status: string, route: bool}
     */
    private function outcome(OrderMessage $message, ?string $reason): array
    {
        if ($reason !== null && (str_starts_with($reason, 'pin_') || in_array($reason, ['rate_limited', 'disabled'], true))) {
            return ['type' => null, 'status' => AgentEvent::STATUS_REJECTED, 'route' => false];
        }
        if (in_array($reason, ['unknown_sender', 'ambiguous_sender'], true)) {
            return ['type' => null, 'status' => AgentEvent::STATUS_TO_SORT, 'route' => false];
        }
        if ($message->status === 'created' && $message->document_id) {
            return ['type' => 'commande_creee', 'status' => AgentEvent::STATUS_NEW, 'route' => true];
        }

        return ['type' => null, 'status' => AgentEvent::STATUS_NEW, 'route' => true];
    }
}
