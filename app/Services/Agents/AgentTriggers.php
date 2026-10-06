<?php

namespace App\Services\Agents;

use App\Models\AgentEvent;
use App\Models\AgentRoutine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Les événements INTERNES d'O3 qui peuvent déclencher une routine (produit créé, stock bas, facture confirmée…).
 *
 * O3 les émet là où ils se produisent (observateurs de modèles, mouvements de stock, dépôt de document) sous
 * forme d'`agent_events` ; une routine « à l'événement » les consomme à son prochain passage du planificateur
 * (RoutineRunner::runDue), au plus une fois par délai (`cooldown_minutes`), et chaque événement une seule fois.
 *
 * Coût nul tant que personne n'écoute : emit() ne fait rien s'il n'existe aucune routine active attendant ce
 * type d'événement (la liste est mémorisée une minute), et ne lève jamais d'exception : un déclencheur ne doit
 * jamais faire échouer une vente, un mouvement de stock ou une création de produit.
 */
class AgentTriggers
{
    /** @var array<string, array{label: string, conditions: array<string, string>}> */
    public const EVENTS = [
        'produit_cree'            => ['label' => 'Un produit est créé', 'conditions' => []],
        'stock_bas'               => ['label' => "Le stock d'un produit atteint le seuil d'alerte (réglage « seuil d'alerte stock », 5 par défaut)", 'conditions' => ['qty_lte' => 'quantité maximale']],
        'facture_vente_confirmee' => ['label' => 'Une facture de vente est confirmée', 'conditions' => ['min_amount' => 'montant TTC minimal']],
        'paiement_recu'           => ['label' => 'Un paiement est enregistré', 'conditions' => ['min_amount' => 'montant minimal']],
        'document_depose'         => ['label' => "Un document est déposé dans l'orchestrateur", 'conditions' => []],
    ];

    private const COOLDOWN_MIN = 15;
    private const COOLDOWN_MAX = 1440;
    private const COOLDOWN_DEFAULT = 60;

    /**
     * Émet un événement interne s'il existe une routine qui l'attend. @param array<string, mixed> $payload
     * @param int|null $dedupeHours ne pas ré-émettre le même événement pour la même entité pendant cette durée
     * @param string|null $entityKey champ de $payload qui identifie l'entité pour la déduplication (ex. product_id)
     */
    public static function emit(string $type, array $payload, ?int $dedupeHours = null, ?string $entityKey = null): void
    {
        try {
            if (!isset(self::EVENTS[$type]) || !function_exists('tenant') || !tenant() || !self::hasListener($type)) {
                return;
            }
            if ($dedupeHours !== null && $entityKey !== null && isset($payload[$entityKey])
                && AgentEvent::where('type', $type)->where('entities->' . $entityKey, $payload[$entityKey])->where('created_at', '>=', now()->subHours($dedupeHours))->exists()) {
                return;
            }

            AgentEvent::create([
                'type'     => $type,
                'source'   => 'erp',
                'status'   => AgentEvent::STATUS_DONE,   // un signal pour les routines, pas un message à classer
                'payload'  => array_merge(['text' => self::EVENTS[$type]['label']], $payload),
                'entities' => $entityKey !== null && isset($payload[$entityKey]) ? [$entityKey => $payload[$entityKey]] : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Déclencheur « {$type} » non émis : {$e->getMessage()}");
        }
    }

    /** Une routine active attend-elle ce type d'événement ? (mémorisé une minute par tenant) */
    public static function hasListener(string $type): bool
    {
        return in_array($type, Cache::remember(self::cacheKey(), 60, function () {
            if (!Schema::hasTable('agent_routines') || !Schema::hasColumn('agent_routines', 'trigger')) {
                return [];
            }

            return AgentRoutine::where('is_active', true)->whereNotNull('trigger')->get()
                ->map(fn (AgentRoutine $r) => $r->trigger['event_type'] ?? null)->filter()->unique()->values()->all();
        }), true);
    }

    /** À appeler quand une routine à l'événement est créée, reprise, mise en pause ou supprimée. */
    public static function forgetListeners(): void
    {
        Cache::forget(self::cacheKey());
    }

    /** Nettoie un déclencheur proposé par l'IA ; null s'il est inutilisable. @return array{event_type: string, conditions: array<string, mixed>, cooldown_minutes: int}|null */
    public static function sanitize(mixed $in): ?array
    {
        if (!is_array($in) || !is_string($in['event_type'] ?? null) || !isset(self::EVENTS[$in['event_type']])) {
            return null;
        }
        $type = $in['event_type'];
        $conditions = [];
        $given = is_array($in['conditions'] ?? null) ? $in['conditions'] : [];

        if (isset(self::EVENTS[$type]['conditions']['qty_lte']) && is_numeric($given['qty_lte'] ?? null)) {
            $conditions['qty_lte'] = max(0, min(1000, (int) $given['qty_lte']));
        }
        if (isset(self::EVENTS[$type]['conditions']['min_amount']) && is_numeric($given['min_amount'] ?? null) && (float) $given['min_amount'] > 0) {
            $conditions['min_amount'] = round(min(1e9, (float) $given['min_amount']), 2);
        }

        $cooldown = is_numeric($in['cooldown_minutes'] ?? null) ? (int) $in['cooldown_minutes'] : self::COOLDOWN_DEFAULT;

        return ['event_type' => $type, 'conditions' => $conditions, 'cooldown_minutes' => max(self::COOLDOWN_MIN, min(self::COOLDOWN_MAX, $cooldown))];
    }

    /** L'événement satisfait-il les conditions du déclencheur ? @param array<string, mixed> $trigger */
    public static function matches(array $trigger, AgentEvent $event): bool
    {
        if ($event->type !== ($trigger['event_type'] ?? null)) {
            return false;
        }
        $c = $trigger['conditions'] ?? [];
        $p = $event->payload ?? [];

        if (isset($c['qty_lte']) && (!isset($p['qty']) || (float) $p['qty'] > (float) $c['qty_lte'])) {
            return false;
        }
        if (isset($c['min_amount']) && (!isset($p['amount']) || (float) $p['amount'] < (float) $c['min_amount'])) {
            return false;
        }

        return true;
    }

    /** « à chaque fois qu'un produit est créé (au plus une fois toutes les 60 min) ». @param array<string, mixed> $trigger */
    public static function describe(array $trigger): string
    {
        $label = mb_strtolower(self::EVENTS[$trigger['event_type']]['label'] ?? $trigger['event_type']);
        $c = $trigger['conditions'] ?? [];
        $extra = [];
        isset($c['qty_lte']) && $extra[] = 'stock de ' . $c['qty_lte'] . ' pièce(s) ou moins';
        isset($c['min_amount']) && $extra[] = 'montant d\'au moins ' . number_format((float) $c['min_amount'], 2, ',', ' ') . ' MAD';

        return "à chaque fois que : {$label}" . ($extra ? ' (' . implode(', ', $extra) . ')' : '')
            . ' — au plus une exécution toutes les ' . self::humanMinutes((int) ($trigger['cooldown_minutes'] ?? self::COOLDOWN_DEFAULT));
    }

    /** Une ligne lisible pour chaque événement déclencheur (donnée aux agents recrutés comme contexte). */
    public static function contextLine(AgentEvent $e): string
    {
        $p = $e->payload ?? [];

        return match ($e->type) {
            'produit_cree'            => 'Produit créé : ' . ($p['title'] ?? '?') . ' (' . ($p['sku'] ?? '?') . ')',
            'stock_bas'               => 'Stock bas : ' . ($p['title'] ?? '?') . ' (' . ($p['sku'] ?? '?') . '), ' . ($p['qty'] ?? '?') . ' pièce(s)' . (isset($p['warehouse']) ? " dans {$p['warehouse']}" : ''),
            'facture_vente_confirmee' => 'Facture ' . ($p['reference'] ?? '?') . ' confirmée' . (isset($p['amount']) ? ' : ' . number_format((float) $p['amount'], 2, ',', ' ') . ' MAD' : ''),
            'paiement_recu'           => 'Paiement' . (isset($p['amount']) ? ' de ' . number_format((float) $p['amount'], 2, ',', ' ') . ' MAD' : '') . ' enregistré',
            default                   => (string) ($p['text'] ?? $e->type),
        };
    }

    private static function humanMinutes(int $m): string
    {
        return $m % 60 === 0 ? ($m / 60) . ' h' : "{$m} min";
    }

    private static function cacheKey(): string
    {
        return 'agent_trigger_listeners:' . (function_exists('tenant') && tenant() ? tenant('id') : 'central');
    }
}
