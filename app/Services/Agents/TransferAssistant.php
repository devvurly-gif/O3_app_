<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\DocumentIncrementor;
use App\Models\Setting;
use App\Models\User;
use App\Services\DocumentHeaderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * « Propose des transferts entre entrepôts » : troisième commande de PROPOSITION de l'orchestrateur.
 *
 * Pour chaque produit au seuil d'alerte dans un entrepôt alors qu'un autre entrepôt en a plus que le nécessaire, il propose de
 * déplacer la quantité qui ramène le premier à DEUX FOIS le seuil, sans jamais descendre l'entrepôt donneur sous ce même niveau.
 * Un seul donneur par produit et par entrepôt receveur : celui qui a le plus gros surplus.
 *
 * Le clic « Créer les bons de transfert » crée un bon de transfert (StockTransfer) BROUILLON par couple donneur → receveur, au
 * nom de l'administrateur. Un brouillon ne déplace aucun stock : le stock ne bouge qu'à l'« application » du bon, un geste
 * séparé dans O3 (document de stock → appliquer). Les produits déjà présents sur un bon de transfert ouvert ne sont pas reproposés.
 * Un produit en manque PARTOUT n'est pas concerné : c'est un réapprovisionnement (ReorderAssistant).
 */
class TransferAssistant
{
    private const TYPE = 'StockTransfer';
    private const CLOSED = ['cancelled', 'applied'];

    public function __construct(private DocumentHeaderService $documents)
    {
    }

    public function propose(User $admin): array
    {
        $warehouses = DB::table('warehouses')->where('wh_status', true)->orderBy('id')->pluck('wh_title', 'id');
        if ($warehouses->count() < 2) {
            return $this->reply('Il faut au moins deux entrepôts actifs pour proposer des transferts : ' . ($warehouses->isEmpty() ? "aucun entrepôt actif n'est configuré." : "un seul entrepôt actif existe ({$warehouses->first()})."));
        }

        $threshold = max(0, min(100, (int) Setting::get('stock', 'seuil_alerte_stock', '5')));
        $target = max(1, $threshold * 2);

        $rows = DB::table('warehouse_has_stock as s')->join('products as p', 'p.id', '=', 's.product_id')->whereNull('p.deleted_at')->where('p.p_status', true)
            ->whereIn('s.warehouse_id', $warehouses->keys())->whereNull('s.variant_id')
            ->get(['s.product_id', 's.warehouse_id', 's.stockLevel', 'p.p_title', 'p.p_sku', 'p.p_unit', 'p.p_purchasePrice'])->groupBy('product_id');

        $open = DB::table('document_lignes as l')->join('document_headers as d', 'd.id', '=', 'l.document_header_id')->whereNull('d.deleted_at')
            ->where('d.document_type', self::TYPE)->whereNotIn('d.status', self::CLOSED)->pluck('l.product_id')->all();

        $pairs = [];
        $lowEverywhere = [];
        foreach ($rows as $productId => $stocks) {
            if (in_array($productId, $open, true)) {
                continue;
            }
            $first = $stocks->first();
            $byWarehouse = $stocks->pluck('stockLevel', 'warehouse_id')->map(fn ($v) => (float) $v);
            foreach ($byWarehouse as $receiverId => $level) {
                if ($level > $threshold) {
                    continue;
                }
                $donors = $byWarehouse->except($receiverId)->map(fn ($v) => $v - $target)->filter(fn ($surplus) => $surplus >= 1)->sortDesc();
                if ($donors->isEmpty()) {
                    $lowEverywhere[$productId] = true;
                    continue;
                }
                $donorId = $donors->keys()->first();
                $quantity = (int) min(ceil($target - $level), floor($donors->first()));
                if ($quantity < 1) {
                    continue;
                }
                $pairs["{$donorId}>{$receiverId}"][] = [
                    'product_id' => $productId, 'reference' => $first->p_sku, 'designation' => $first->p_title, 'unit' => $first->p_unit ?: 'pièce',
                    'quantity' => $quantity, 'unit_price' => round((float) $first->p_purchasePrice, 2),
                    'donor_stock' => $byWarehouse[$donorId], 'receiver_stock' => $level,
                ];
            }
        }

        $lowEverywhere = count($lowEverywhere);

        if ($pairs === []) {
            return $this->reply("Aucun transfert utile : aucun produit au seuil d'alerte ({$threshold} pièce(s)) dans un entrepôt n'a de surplus ailleurs (un entrepôt donneur doit garder {$target} pièce(s))."
                . ($lowEverywhere > 0 ? " {$lowEverywhere} produit(s) sont bas partout : c'est un réapprovisionnement (« réapprovisionne le stock faible »)." : ''));
        }

        $items = [];
        $lines = [];
        foreach ($pairs as $key => $rowsOfPair) {
            [$donorId, $receiverId] = array_map('intval', explode('>', $key));
            $items[] = ['from_id' => $donorId, 'from' => $warehouses[$donorId], 'to_id' => $receiverId, 'to' => $warehouses[$receiverId], 'lines' => $rowsOfPair];
            $lines[] = "• {$warehouses[$donorId]} → {$warehouses[$receiverId]} — " . count($rowsOfPair) . ' produit(s)';
            foreach (array_slice($rowsOfPair, 0, 3) as $r) {
                $lines[] = "    – {$r['designation']} : {$r['quantity']} à déplacer (donneur " . $this->num($r['donor_stock']) . ', receveur ' . $this->num($r['receiver_stock']) . ')';
            }
            count($rowsOfPair) > 3 && $lines[] = '    – … et ' . (count($rowsOfPair) - 3) . ' autre(s)';
        }

        $event = AgentEvent::create([
            'type' => 'transfert_entrepots', 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_ROUTED, 'agent_id' => Agent::where('domain', 'stocks')->value('id'),
            'payload' => ['text' => 'Transferts : ' . count($items) . ' bon(s) de transfert à préparer', 'items' => $items, 'threshold' => $threshold, 'target' => $target, 'requested_by' => $admin->name],
        ]);

        return $this->reply(
            count($items) . " bon(s) de transfert à préparer (lot #{$event->id}) ; chaque entrepôt receveur est ramené à {$target} pièce(s) sans faire descendre le donneur sous ce niveau :\n\n" . implode("\n", $lines)
            . ($lowEverywhere > 0 ? "\n\n{$lowEverywhere} autre(s) produit(s) sont bas partout : voir « réapprovisionne le stock faible »." : '')
            . "\n\nLe bouton crée des bons de transfert BROUILLONS à votre nom : le stock ne bouge qu'au moment où vous appliquez chaque bon dans O3 (stock → documents).",
            [['label' => 'Créer les bons de transfert', 'text' => "applique le lot #{$event->id}"], ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"]],
            $event->id,
        );
    }

    /** Crée un bon de transfert brouillon par couple donneur → receveur. */
    public function apply(User $admin, AgentEvent $event): array
    {
        $incrementor = DocumentIncrementor::where('di_model', self::TYPE)->first();
        if (!$incrementor) {
            return $this->reply("Impossible de créer les bons : l'incrémenteur des bons de transfert n'existe pas. Le lot reste en attente.", eventId: $event->id);
        }

        $created = [];
        foreach ($event->payload['items'] ?? [] as $item) {
            $lines = array_map(fn ($l) => [
                'product_id' => $l['product_id'], 'designation' => $l['designation'], 'reference' => $l['reference'], 'quantity' => $l['quantity'], 'unit' => $l['unit'],
                'unit_price' => $l['unit_price'], 'discount_percent' => 0, 'tax_percent' => 0,
            ], $item['lines']);

            $document = $this->documents->createWithLinesAndFooter([
                'document_incrementor_id' => $incrementor->id, 'document_type' => self::TYPE, 'document_title' => 'Bon de Transfert',
                'warehouse_id' => $item['from_id'], 'warehouse_dest_id' => $item['to_id'], 'issued_at' => Carbon::today(), 'due_at' => null,
                'notes' => "Brouillon préparé par l'orchestrateur ({$item['from']} → {$item['to']}), validé par {$admin->name}. Le stock ne bouge qu'à l'application du bon.",
                'user_id' => $admin->id,
            ], $lines, null);
            $created[] = ['reference' => $document->reference, 'from' => $item['from'], 'to' => $item['to'], 'document_id' => $document->id];
        }

        $event->update(['status' => AgentEvent::STATUS_DONE, 'payload' => array_merge($event->payload ?? [], ['created' => $created, 'applied_by' => $admin->name])]);
        AgentAction::create(['agent_id' => $event->agent_id, 'event_id' => $event->id, 'action' => 'transfer_drafts_created', 'level' => 'approval', 'input' => ['requested_by' => $event->payload['requested_by'] ?? null], 'result' => ['documents' => array_column($created, 'reference')]]);

        return $this->reply(count($created) . ' bon(s) de transfert brouillon créé(s) : ' . implode(', ', array_map(fn ($c) => "{$c['reference']} ({$c['from']} → {$c['to']})", $created))
            . ". Aucun stock n'a bougé ; relisez-les puis appliquez-les dans O3 (stock → documents).", eventId: $event->id);
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', ' '), '0'), ',');
    }

    /**
     * @param array<int, array{label: string, text: string}> $suggestions
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, array $suggestions = [], ?int $eventId = null): array
    {
        return ['body' => $body, 'meta' => array_filter(['intent' => 'catalog', 'suggestions' => $suggestions ?: null, 'event_id' => $eventId], fn ($v) => $v !== null)];
    }
}
