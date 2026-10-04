<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentCase;
use App\Models\AgentEvent;
use App\Models\OrchestratorMessage;
use App\Models\PaymentReminder;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Str;

/**
 * L'orchestrateur : le « chef » à qui un administrateur parle. Il comprend une
 * liste définie de demandes en français (règles, sans modèle de langage), les
 * confie aux agents, et répond avec ce qui s'est passé.
 *
 * Garde-fous :
 *  - il ne peut déclencher que des ordres qui existent (AgentOrderService) : des
 *    brouillons, jamais une écriture irréversible ;
 *  - une simple question ne déclenche rien : il faut un verbe d'action
 *    (« prépare », « lance », « contrôle »…) pour qu'un ordre parte ;
 *  - la validation humaine (relances, application des écarts d'inventaire) reste
 *    dans les écrans dédiés, vers lesquels il renvoie ;
 *  - ce qu'il ne comprend pas, il le dit et rappelle ce qu'il sait faire.
 */
class Orchestrator
{
    public function __construct(private AgentOrderService $orders, private OrchestratorInterpreter $interpreter)
    {
    }

    /** @return array{user: OrchestratorMessage, reply: OrchestratorMessage} */
    public function converse(User $admin, string $text): array
    {
        $text = trim($text);
        $user = OrchestratorMessage::create(['user_id' => $admin->id, 'role' => OrchestratorMessage::ROLE_ADMIN, 'body' => $text]);

        $answer = $this->answer($admin, $text);

        $reply = OrchestratorMessage::create([
            'user_id' => $admin->id,
            'role'    => OrchestratorMessage::ROLE_ORCHESTRATOR,
            'body'    => $answer['body'],
            'meta'    => $answer['meta'],
        ]);

        return ['user' => $user, 'reply' => $reply];
    }

    /** @return array{body: string, meta: array<string, mixed>} */
    private function answer(User $admin, string $text): array
    {
        $n = $this->normalize($text);
        $action = $this->wantsAction($n);

        return match (true) {
            (bool) preg_match('/inventaire|comptage/', $n)                                        => $this->inventory($admin, $n, $action),
            (bool) preg_match('/encaissement|impaye|recouvrement|relance|paiements? en retard/', $n) => $this->collections($admin, $n, $action),
            (bool) preg_match('/\ba trier\b|non classe|evenement|messages? recus?/', $n)          => $this->toSort(),
            (bool) preg_match('/\b(etat|statut|situation|bilan|resume|point|agents?|orchestr|ou en)\b/', $n) => $this->status(),
            (bool) preg_match('/\b(aide|help|bonjour|salut|bonsoir|coucou|que peux|que sais|commandes?)\b/', $n) => $this->help(true),
            default                                                                               => $this->freeText($admin, $text),
        };
    }

    // ── Phrase libre (renfort par un modèle de langage, si activé) ───

    /**
     * Les règles n'ont rien compris. Si la compréhension avancée est activée, le modèle range la
     * phrase dans une demande connue ; sinon (ou en cas d'échec) l'aide habituelle s'affiche.
     * Une demande de lecture est traitée directement ; un ordre est seulement PROPOSÉ : il ne part
     * que sur le clic de l'administrateur, qui envoie la phrase canonique traitée par les règles.
     */
    private function freeText(User $admin, string $text): array
    {
        $titles = Warehouse::where('wh_status', true)->pluck('wh_title')->all();
        $r = $this->interpreter->interpret($text, $titles);

        if ($r === null) {
            $why = $this->interpreter->failure();

            // IA activée mais en échec : on le dit (la phrase n'est pas « incomprise », elle n'a pas pu être lue).
            return $why ? $this->help(false, "La compréhension avancée est indisponible : {$why}.") : $this->help(false);
        }

        $answer = match ($r['intent']) {
            'etat'            => $this->status(),
            'a_trier'         => $this->toSort(),
            'inventaire_etat' => $this->inventory($admin, '', false),
            'relances_etat'   => $this->collections($admin, '', false),
            'inventaire_ordre' => $this->propose(
                'préparer un inventaire' . ($r['warehouse'] ? " de l'entrepôt « {$r['warehouse']} »" : ' de tous les entrepôts') . ($r['scope'] === 'attention' ? ', limité aux articles à vérifier' : ''),
                'inventory',
                'prépare un inventaire' . ($r['warehouse'] ? " du {$r['warehouse']}" : '') . ($r['scope'] === 'attention' ? ' des articles à vérifier' : ''),
            ),
            'encaissements_ordre' => $this->propose('contrôler les encaissements et préparer les relances', 'collections', 'contrôle les encaissements'),
            'aide'            => $this->help(true),
            default           => $this->help(false),
        };

        $answer['meta']['ai'] = true;

        return $answer;
    }

    /** Un ordre déduit d'une phrase libre n'est jamais lancé seul : on propose, l'administrateur confirme. */
    private function propose(string $what, string $intent, string $command): array
    {
        return $this->reply(
            "J'ai compris que vous voulez {$what}. Un ordre ne part que sur une demande explicite : le confirmez-vous ?",
            $intent . '_proposal',
            suggestions: [['label' => 'Oui, ' . $command, 'text' => $command]],
        );
    }

    // ── Demandes ─────────────────────────────────────────────────────

    private function help(bool $greeting, ?string $warning = null): array
    {
        $intro = $warning !== null
            ? "{$warning}\nJe ne peux donc traiter que ce que je comprends sans elle :"
            : ($greeting
                ? "Bonjour. Je suis l'orchestrateur : je reçois vos demandes et je les confie aux agents. Voici ce que je sais faire."
                : "Je n'ai pas compris cette demande. Voici ce que je sais faire.");

        return $this->reply(
            "{$intro}\n\n"
            . "• « état des agents » : la situation des agents, des événements et des validations en attente\n"
            . "• « événements à trier » : les messages que le routeur n'a su confier à personne\n"
            . "• « prépare un inventaire » : l'agent Stocks prépare la feuille à compter (ajoutez un nom d'entrepôt, ou « articles à vérifier » pour cibler)\n"
            . "• « contrôle les encaissements » : l'agent Recouvrement contrôle les paiements et prépare les relances\n"
            . "• « relances à valider » : ce qui attend votre validation\n\n"
            . "Les agents préparent des brouillons. Rien n'est modifié ni envoyé sans votre validation, dans l'écran concerné.",
            'help',
            warning: $warning !== null,
        );
    }

    private function status(): array
    {
        $agents = Agent::orderBy('id')->get();
        if ($agents->isEmpty()) {
            return $this->reply("Aucun agent n'est encore enregistré sur ce tenant : le socle n'a pas été initialisé (seeder des agents).", 'status', error: true);
        }

        $active = $agents->where('is_active', true)->pluck('name')->implode(', ') ?: 'aucun';
        $inactive = $agents->where('is_active', false)->pluck('name')->implode(', ') ?: 'aucun';
        $toSort = AgentEvent::where('status', AgentEvent::STATUS_TO_SORT)->count();
        $rejected = AgentEvent::where('status', AgentEvent::STATUS_REJECTED)->where('created_at', '>=', now()->subDay())->count();
        $reminders = PaymentReminder::whereIn('status', [PaymentReminder::STATUS_DRAFT, PaymentReminder::STATUS_FAILED]);
        $toValidate = (clone $reminders)->count();
        $amount = (float) (clone $reminders)->sum('amount_due');
        $sheets = $this->inventorySheets();

        $body = "Situation du " . now()->format('d/m à H:i') . " :\n\n"
            . "• Agents actifs : {$active}\n"
            . "• Agents inactifs : {$inactive}\n"
            . '• Routeur de messages : ' . (Setting::get('agents', 'router_enabled', 'false') === 'true' ? 'activé' : 'désactivé') . "\n"
            . '• Événements : ' . AgentEvent::count() . " au total, {$toSort} à trier, {$rejected} refusé(s) ces dernières 24 h\n"
            . "• Dossiers ouverts : " . AgentCase::where('status', 'open')->count() . "\n"
            . "• Relances à valider : {$toValidate}" . ($toValidate ? ' (' . $this->money($amount) . ')' : '') . "\n"
            . "• Feuilles d'inventaire à compter : {$sheets['to_count']}";

        return $this->reply($body, 'status', links: [
            ['label' => 'Activité des agents', 'to' => '/settings/agents'],
            ['label' => 'Relances de paiement', 'to' => '/ventes/relances'],
            ['label' => 'Inventaire', 'to' => '/stock/inventaire'],
        ]);
    }

    private function toSort(): array
    {
        $events = AgentEvent::where('status', AgentEvent::STATUS_TO_SORT)->latest('id')->limit(5)->get();
        if ($events->isEmpty()) {
            return $this->reply('Rien à trier : le routeur a confié tous les événements à un agent (ou les a refusés).', 'to_sort');
        }

        $lines = $events->map(function (AgentEvent $e) {
            $text = Str::limit((string) ($e->payload['text'] ?? '—'), 70);

            return "• #{$e->id} · {$e->source} · {$text}";
        })->implode("\n");
        $total = AgentEvent::where('status', AgentEvent::STATUS_TO_SORT)->count();

        return $this->reply(
            "{$total} événement(s) à trier. Les " . $events->count() . " plus récents :\n\n{$lines}",
            'to_sort',
            links: [['label' => 'Voir tous les événements', 'to' => '/settings/agents']],
        );
    }

    private function inventory(User $admin, string $n, bool $action): array
    {
        if (!$action) {
            $s = $this->inventorySheets();

            return $this->reply(
                "Feuilles d'inventaire : {$s['total']} préparée(s), {$s['to_count']} à compter, {$s['applied']} appliquée(s).\n\n"
                . "Pour en préparer une, dites par exemple « prépare un inventaire », « prépare un inventaire du Dépôt Principal » ou « prépare un inventaire des articles à vérifier ».",
                'inventory_status',
                links: [['label' => 'Ouvrir Inventaire', 'to' => '/stock/inventaire']],
            );
        }

        $warehouse = Warehouse::where('wh_status', true)->get()->first(fn (Warehouse $w) => str_contains($n, $this->normalize($w->wh_title)));
        $scope = preg_match('/a verifier|seulement|uniquement|anomalie|attention/', $n) ? 'attention' : 'all';

        $out = $this->orders->orderInventory([
            'warehouse_id' => $warehouse?->id,
            'scope'        => $scope,
            'note'         => 'Demandé à l\'orchestrateur',
        ], $admin->name);

        if (!$out['ok']) {
            return $this->reply($out['message'], 'inventory', error: true, eventId: $out['event']->id);
        }

        $r = $out['result'];
        $where = $warehouse ? "l'entrepôt « {$warehouse->wh_title} »" : 'tous les entrepôts';
        $body = "J'ai confié l'ordre à l'agent Stocks (événement #{$out['event']->id}) pour {$where}.\n\n"
            . "Feuille prête : {$r['rows']} article(s), dont {$r['flagged']} à vérifier.\n"
            . "Rien n'est modifié : vous comptez, puis vous appliquez vous-même les écarts dans l'écran Inventaire.";
        if ($r['rows'] === 0) {
            $body = "J'ai confié l'ordre à l'agent Stocks (événement #{$out['event']->id}) pour {$where}, mais aucun article ne correspond"
                . ($scope === 'attention' ? ' : aucun n\'est à vérifier.' : ' : aucun stock enregistré.');
        }

        return $this->reply($body, 'inventory', links: [['label' => 'Ouvrir Inventaire', 'to' => '/stock/inventaire']], eventId: $out['event']->id);
    }

    private function collections(User $admin, string $n, bool $action): array
    {
        if (!$action) {
            $drafts = PaymentReminder::with(['document:id,reference', 'thirdPartner:id,tp_title'])
                ->whereIn('status', [PaymentReminder::STATUS_DRAFT, PaymentReminder::STATUS_FAILED])->latest('id')->limit(3)->get();
            $count = PaymentReminder::whereIn('status', [PaymentReminder::STATUS_DRAFT, PaymentReminder::STATUS_FAILED])->count();

            if ($count === 0) {
                return $this->reply(
                    "Aucune relance n'attend votre validation.\n\nPour que l'agent Recouvrement contrôle les encaissements et prépare les relances, dites « contrôle les encaissements ».",
                    'reminders_status',
                    links: [['label' => 'Relances de paiement', 'to' => '/ventes/relances']],
                );
            }

            $lines = $drafts->map(fn (PaymentReminder $r) => "• {$r->thirdPartner?->tp_title} · {$r->document?->reference} · " . $this->money((float) $r->amount_due) . " · niveau {$r->level}")->implode("\n");

            return $this->reply(
                "{$count} relance(s) attendent votre validation. Les plus récentes :\n\n{$lines}\n\nValidez, modifiez ou rejetez-les dans l'écran Relances de paiement : seul votre geste envoie un message.",
                'reminders_status',
                links: [['label' => 'Relances de paiement', 'to' => '/ventes/relances']],
            );
        }

        $out = $this->orders->orderCollections($admin->name);
        if (!$out['ok']) {
            return $this->reply($out['message'], 'collections', error: true, eventId: $out['event']->id);
        }

        $r = $out['result'];
        $created = count($r['created']);
        $body = "J'ai confié l'ordre à l'agent Recouvrement (événement #{$out['event']->id}).\n\n"
            . "• {$r['overdue']} facture(s) en retard\n"
            . "• {$created} relance(s) préparée(s) en brouillon\n"
            . '• ' . count($r['anomalies']) . " anomalie(s) d'encaissement";
        foreach (array_slice($r['anomalies'], 0, 3) as $a) {
            $body .= "\n   – {$a['reference']} : {$a['message']}";
        }
        $body .= "\n\nAucun message n'est parti : validez les relances dans l'écran Relances de paiement.";

        return $this->reply($body, 'collections', links: [['label' => 'Relances de paiement', 'to' => '/ventes/relances']], eventId: $out['event']->id);
    }

    // ── Outils ───────────────────────────────────────────────────────

    /** @return array{total: int, to_count: int, applied: int} */
    private function inventorySheets(): array
    {
        $events = AgentEvent::where('type', 'inventaire_demande')->pluck('id');
        $prepared = \App\Models\AgentAction::whereIn('event_id', $events)->where('action', 'prepare_inventory_sheet')->pluck('event_id')->unique();
        $applied = \App\Models\AgentAction::whereIn('event_id', $events)->where('action', 'inventory_applied')->pluck('event_id')->unique();

        return ['total' => $prepared->count(), 'applied' => $applied->count(), 'to_count' => $prepared->diff($applied)->count()];
    }

    /** Un verbe d'action est nécessaire pour qu'un ordre parte ; « à vérifier » seul n'en est pas un. */
    private function wantsAction(string $n): bool
    {
        $n = str_replace('a verifier', '', $n);

        return (bool) preg_match('/\b(prepar|lanc|fais|faire|genere|demand|control|verifi|execut|realis|organis)\w*/', $n);
    }

    private function normalize(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' MAD';
    }

    /**
     * @param array<int, array{label: string, to: string}> $links
     * @param array<int, array{label: string, text: string}> $suggestions boutons proposés : le texte est envoyé tel quel en cas de clic
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, string $intent, array $links = [], bool $error = false, ?int $eventId = null, array $suggestions = [], bool $warning = false): array
    {
        return ['body' => $body, 'meta' => array_filter([
            'intent'   => $intent,
            'links'    => $links ?: null,
            'error'    => $error ?: null,
            'event_id'    => $eventId,
            'suggestions' => $suggestions ?: null,
            'warning'     => $warning ?: null,
        ], fn ($v) => $v !== null)];
    }
}
