<?php

namespace App\Services\Agents;

use App\Models\AgentEvent;

/**
 * Réserve un lot ou une proposition pendant qu'on l'applique, pour qu'un double clic (deux onglets, deux administrateurs)
 * ne l'applique pas deux fois.
 *
 * Une seule instruction SQL fait passer le lot de « en attente » à « en cours » : la base garantit qu'une seule des requêtes
 * simultanées la gagne. Cela ne dépend d'aucun cache (un verrou de cache échoue sur un cache sans étiquettes, que la séparation
 * des tenants exige) et vaut pour plusieurs serveurs. Une réservation abandonnée (processus tué en cours de route) redevient
 * prenable au bout de cinq minutes.
 */
final class LotClaim
{
    private const STALE_MINUTES = 5;

    /** Vrai si CETTE requête vient de réserver le lot ; faux s'il est déjà pris, traité, ignoré ou inexistant. */
    public static function take(int $eventId): bool
    {
        return AgentEvent::whereKey($eventId)
            ->where(function ($q) {
                $q->where('status', AgentEvent::STATUS_ROUTED)
                    ->orWhere(fn ($stale) => $stale->where('status', AgentEvent::STATUS_IN_PROGRESS)->where('updated_at', '<', now()->subMinutes(self::STALE_MINUTES)));
            })
            ->update(['status' => AgentEvent::STATUS_IN_PROGRESS, 'updated_at' => now()]) === 1;
    }

    /** Remet « en attente » un lot réservé que l'application n'a pas terminé (refus, erreur) ; un lot traité ne bouge pas. */
    public static function release(int $eventId): void
    {
        AgentEvent::whereKey($eventId)->where('status', AgentEvent::STATUS_IN_PROGRESS)->update(['status' => AgentEvent::STATUS_ROUTED]);
    }

    /** Un lot réservé il y a moins de cinq minutes est « en cours de traitement ». */
    public static function isBeingProcessed(int $eventId): bool
    {
        return AgentEvent::whereKey($eventId)->where('status', AgentEvent::STATUS_IN_PROGRESS)->where('updated_at', '>=', now()->subMinutes(self::STALE_MINUTES))->exists();
    }
}
