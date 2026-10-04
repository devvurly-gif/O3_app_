<?php

namespace App\Services\Agents;

use App\Exports\StockInventorySheetExport;
use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Agent Stocks : prépare une feuille d'inventaire (brouillon) à partir d'un
 * ordre `inventaire_demande`. Il ne modifie jamais le stock : l'équipe compte,
 * puis ajuste elle-même (POST /api/stock/ajustement).
 *
 * Ordre (payload de l'événement) :
 *   warehouse_id : un entrepôt, ou absent pour tous les entrepôts actifs
 *   scope        : all (tous les articles en stock) | attention (seulement ceux à vérifier)
 *
 * « À vérifier » : stock négatif, stock nul, mouvement de stock en attente,
 * ou article dormant (stock > 0 sans mouvement depuis DORMANT_DAYS jours). Un article
 * inactif est compté comme les autres, sans être signalé pour cela.
 */
class StockInventoryAgent
{
    public const DORMANT_DAYS = 90;
    public const SCOPES = ['all', 'attention'];

    /**
     * `lines` : les mêmes articles sous forme exploitable (comptage à l'écran, application des écarts).
     *
     * @return array{path: string, name: string, rows: int, flagged: int, warehouses: array<int, string>, lines: array<int, array<string, mixed>>}
     */
    public function handle(AgentEvent $event): array
    {
        $order = $event->payload['order'] ?? [];
        $warehouseId = isset($order['warehouse_id']) ? (int) $order['warehouse_id'] : null;
        $scope = in_array($order['scope'] ?? 'all', self::SCOPES, true) ? $order['scope'] : 'all';

        $entries = $this->entries($warehouseId, $scope);
        $rows = array_column($entries, 'row');
        $lines = array_column($entries, 'line');
        $flagged = count(array_filter($rows, fn (array $r) => $r[7] !== ''));

        $name = sprintf('inventaire-%d-%s.xlsx', $event->id, now()->format('Ymd-His'));
        $path = 'agents/inventaires/' . $name;
        Excel::store(new StockInventorySheetExport($rows), $path, 'local');

        $warehouses = array_values(array_unique(array_column($rows, 0)));
        $result = ['path' => $path, 'name' => $name, 'rows' => count($rows), 'flagged' => $flagged, 'warehouses' => $warehouses, 'lines' => $lines];

        AgentAction::create([
            'agent_id' => Agent::where('domain', 'stocks')->value('id'),
            'event_id' => $event->id,
            'case_id'  => $event->case_id,
            'action'   => 'prepare_inventory_sheet',
            'level'    => 'approval',
            'input'    => ['warehouse_id' => $warehouseId, 'scope' => $scope],
            'result'   => $result,
        ]);

        return $result;
    }

    /** @return array<int, array{row: array<int, mixed>, line: array<string, mixed>}> */
    private function entries(?int $warehouseId, string $scope): array
    {
        $stock = DB::table('warehouse_has_stock as s')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->join('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            // Pas de filtre sur p_status : un article inactif qui a du stock existe physiquement,
            // il doit être compté. Il n'est pas non plus signalé pour ce seul motif : l'import crée
            // les produits inactifs (fiche à compléter), donc c'est l'état normal du catalogue.
            ->whereNull('p.deleted_at')
            ->where('w.wh_status', true)
            ->when($warehouseId, fn ($q) => $q->where('s.warehouse_id', $warehouseId))
            ->get(['w.wh_title', 's.warehouse_id', 'p.id as product_id', 'p.p_sku', 'p.p_title', 'c.ctg_title', 's.stockLevel']);

        $pending = DB::table('stock_mouvements')
            ->where('status', 'pending')
            ->selectRaw("product_id, warehouse_id, SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as net")
            ->groupBy('product_id', 'warehouse_id')
            ->get()->keyBy(fn ($r) => $r->product_id . ':' . $r->warehouse_id);

        $last = DB::table('stock_mouvements')
            ->where('status', '!=', 'cancelled')
            ->selectRaw('product_id, warehouse_id, MAX(created_at) as last_at')
            ->groupBy('product_id', 'warehouse_id')
            ->get()->keyBy(fn ($r) => $r->product_id . ':' . $r->warehouse_id);

        $dormantBefore = now()->subDays(self::DORMANT_DAYS);
        $entries = [];

        foreach ($stock as $s) {
            $key = $s->product_id . ':' . $s->warehouse_id;
            $level = (float) $s->stockLevel;
            $net = $pending->has($key) ? (float) $pending[$key]->net : 0.0;
            $lastAt = $last->has($key) ? $last[$key]->last_at : null;

            $flags = [];
            if ($level < 0) {
                $flags[] = 'Stock négatif';
            } elseif ($level == 0.0) {
                $flags[] = 'Stock nul';
            }
            if ($net != 0.0) {
                $flags[] = 'Mouvement en attente';
            }
            if ($level > 0 && (!$lastAt || $lastAt < $dormantBefore->toDateTimeString())) {
                $flags[] = 'Dormant ' . self::DORMANT_DAYS . ' j';
            }

            if ($scope === 'attention' && !$flags) {
                continue;
            }

            $entries[] = [
                'row'  => [
                    $s->wh_title, $s->p_sku, $s->p_title, $s->ctg_title ?? '', $level,
                    $net != 0.0 ? $net : '', $lastAt ? substr($lastAt, 0, 10) : '', implode(', ', $flags),
                    '', '', // Quantité comptée, Commentaire (l'Écart est inséré par l'export)
                ],
                'line' => [
                    'warehouse_id' => (int) $s->warehouse_id, 'warehouse' => $s->wh_title,
                    'product_id' => (int) $s->product_id, 'sku' => $s->p_sku, 'title' => $s->p_title,
                    'theoretical' => $level, 'pending' => $net, 'flags' => $flags,
                ],
            ];
        }

        // Les articles à vérifier d'abord, puis par entrepôt et SKU.
        usort($entries, fn (array $a, array $b) => [$a['row'][7] === '', $a['row'][0], $a['row'][1]] <=> [$b['row'][7] === '', $b['row'][0], $b['row'][1]]);

        return $entries;
    }
}
