<?php

namespace App\Http\Controllers\Api\Stock;

use App\Http\Controllers\Controller;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\WarehouseHasStock;
use App\Services\Agents\AgentOrderService;
use App\Services\Agents\StockInventoryAgent;
use App\Services\StockOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Écran « Inventaire » du menu Stock : feuilles préparées par l'agent Stocks,
 * comptage à l'écran, puis application des écarts par un humain.
 *
 * L'agent ne modifie jamais le stock. Seul `apply()`, déclenché par l'utilisateur,
 * ajuste les quantités (StockOperationService::adjustInventory) et il n'est
 * possible qu'une fois par feuille.
 */
class InventoryController extends Controller
{
    public function __construct(private AgentOrderService $orders, private StockOperationService $stockOps)
    {
    }

    /** GET /api/stock/inventaires — les feuilles, la plus récente d'abord. */
    public function index(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $events = AgentEvent::where('type', 'inventaire_demande')->latest('id')->limit(50)->get();
        $actions = AgentAction::whereIn('event_id', $events->pluck('id'))
            ->whereIn('action', ['prepare_inventory_sheet', 'inventory_applied'])
            ->get()->groupBy('event_id');

        return response()->json([
            'sessions' => $events->map(fn (AgentEvent $e) => $this->summary($e, $actions->get($e->id, collect())))->values(),
        ]);
    }

    /** POST /api/stock/inventaires — l'agent Stocks prépare une nouvelle feuille. */
    public function store(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'scope'        => ['nullable', 'in:' . implode(',', StockInventoryAgent::SCOPES)],
            'note'         => ['nullable', 'string', 'max:500'],
        ]);

        $out = $this->orders->orderInventory($data, $request->user()?->name);
        if (!$out['ok']) {
            return response()->json(['message' => $out['message'], 'event_id' => $out['event']->id], $out['http']);
        }

        $actions = AgentAction::where('event_id', $out['event']->id)->get();

        return response()->json($this->summary($out['event'], $actions), 201);
    }

    /** GET /api/stock/inventaires/{event} — les lignes à compter, avec le stock actuel. */
    public function show(Request $request, AgentEvent $event): JsonResponse
    {
        $this->ensureInteractiveUser($request);
        $prepared = $this->preparedAction($event);

        $lines = collect($prepared->result['lines'] ?? []);
        $current = WarehouseHasStock::whereIn('product_id', $lines->pluck('product_id')->unique())
            ->get(['warehouse_id', 'product_id', 'stockLevel'])
            ->keyBy(fn ($s) => $s->warehouse_id . ':' . $s->product_id);

        $actions = AgentAction::where('event_id', $event->id)->get();

        return response()->json([
            'session' => $this->summary($event, $actions),
            'lines'   => $lines->map(fn (array $l) => $l + [
                'current' => (float) ($current->get($l['warehouse_id'] . ':' . $l['product_id'])?->stockLevel ?? 0),
            ])->values(),
        ]);
    }

    /** POST /api/stock/inventaires/{event}/appliquer — ajuste le stock aux quantités comptées. */
    public function apply(Request $request, AgentEvent $event): JsonResponse
    {
        $this->ensureInteractiveUser($request);
        $prepared = $this->preparedAction($event);

        if (AgentAction::where('event_id', $event->id)->where('action', 'inventory_applied')->exists()) {
            return response()->json(['message' => 'Les écarts de cette feuille ont déjà été appliqués.'], 422);
        }

        $data = $request->validate([
            'counts'                => ['required', 'array', 'min:1', 'max:5000'],
            'counts.*.warehouse_id' => ['required', 'integer'],
            'counts.*.product_id'   => ['required', 'integer'],
            'counts.*.counted'      => ['required', 'numeric', 'min:0'],
        ]);

        // Seules les lignes de la feuille peuvent être ajustées.
        $allowed = collect($prepared->result['lines'] ?? [])->keyBy(fn (array $l) => $l['warehouse_id'] . ':' . $l['product_id']);

        $adjusted = 0;
        $unchanged = 0;
        $errors = [];

        foreach ($data['counts'] as $c) {
            $key = $c['warehouse_id'] . ':' . $c['product_id'];
            $line = $allowed->get($key);
            if (!$line) {
                $errors[] = ['product_id' => $c['product_id'], 'message' => "Ligne absente de la feuille d'inventaire."];
                continue;
            }

            try {
                $this->stockOps->adjustInventory(
                    (int) $c['product_id'],
                    (int) $c['warehouse_id'],
                    (float) $c['counted'],
                    $request->user()->id,
                    "Inventaire #{$event->id} : théorique {$line['theoretical']}, compté {$c['counted']}",
                );
                $adjusted++;
            } catch (\DomainException) {
                $unchanged++;   // déjà à la quantité comptée
            } catch (\Throwable $e) {
                $errors[] = ['product_id' => $c['product_id'], 'message' => $e->getMessage()];
            }
        }

        AgentAction::create([
            'agent_id' => $prepared->agent_id,
            'event_id' => $event->id,
            'case_id'  => $event->case_id,
            'action'   => 'inventory_applied',
            'level'    => 'approval',
            'input'    => ['lines' => count($data['counts'])],
            'result'   => ['adjusted' => $adjusted, 'unchanged' => $unchanged, 'errors' => $errors, 'by' => $request->user()->name],
        ]);

        return response()->json(['adjusted' => $adjusted, 'unchanged' => $unchanged, 'errors' => $errors]);
    }

    /** GET /api/stock/inventaires/{event}/fichier — la feuille Excel. */
    public function file(Request $request, AgentEvent $event): StreamedResponse
    {
        $this->ensureInteractiveUser($request);
        $path = $this->preparedAction($event)->result['path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404, 'Fichier introuvable.');

        return Storage::disk('local')->download($path, $this->preparedAction($event)->result['name']);
    }

    private function preparedAction(AgentEvent $event): AgentAction
    {
        abort_unless($event->type === 'inventaire_demande', 404);

        return AgentAction::where('event_id', $event->id)->where('action', 'prepare_inventory_sheet')->latest('id')->firstOr(
            fn () => abort(404, "Cette feuille d'inventaire n'existe pas.")
        );
    }

    /** @param \Illuminate\Support\Collection<int, AgentAction> $actions */
    private function summary(AgentEvent $event, $actions): array
    {
        $prepared = $actions->firstWhere('action', 'prepare_inventory_sheet');
        $applied = $actions->firstWhere('action', 'inventory_applied');

        return [
            'event_id'   => $event->id,
            'created_at' => $event->created_at,
            'ordered_by' => $event->payload['ordered_by'] ?? null,
            'scope'      => $event->payload['order']['scope'] ?? 'all',
            'note'       => $event->payload['order']['note'] ?? null,
            'status'     => $applied ? 'applied' : ($prepared ? 'prepared' : $event->status),
            'warehouses' => $prepared->result['warehouses'] ?? [],
            'rows'       => $prepared->result['rows'] ?? 0,
            'flagged'    => $prepared->result['flagged'] ?? 0,
            'file_name'  => $prepared->result['name'] ?? null,
            'applied_at' => $applied?->created_at,
            'applied_by' => $applied->result['by'] ?? null,
            'adjusted'   => $applied->result['adjusted'] ?? null,
        ];
    }

    private function ensureInteractiveUser(Request $request): void
    {
        // Jeton de connexion (abilities « * ») ou session : autorisé. Jeton à
        // abilities restreintes (agents IA) : refusé.
        $token = $request->user()?->currentAccessToken();
        abort_if($token && !$token->can('stock:inventory'), 403, 'Réservé aux utilisateurs connectés.');
    }
}
