<?php

namespace App\Http\Controllers\Api\Agents;

use App\Http\Controllers\Controller;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Services\Agents\AgentOrderService;
use App\Services\Agents\StockInventoryAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ordres donnés à un agent depuis l'écran « Activité des agents ».
 * L'exécution est dans AgentOrderService : l'ordre devient un événement manuel
 * routé vers l'agent concerné, qui prépare un brouillon (ici une feuille
 * d'inventaire) sans rien modifier.
 */
class AgentOrderController extends Controller
{
    public function __construct(private AgentOrderService $orders)
    {
    }

    /** POST /api/agents/ordres */
    public function store(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $data = $request->validate([
            'type'         => ['required', 'in:inventaire'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'scope'        => ['nullable', 'in:' . implode(',', StockInventoryAgent::SCOPES)],
            'note'         => ['nullable', 'string', 'max:500'],
        ]);

        $out = $this->orders->orderInventory($data, $request->user()?->name);

        if (!$out['ok']) {
            return response()->json([
                'message'  => $out['message'],
                'event_id' => $out['event']->id,
                'status'   => $out['event']->status,
            ], $out['http']);
        }

        $result = $out['result'];

        return response()->json([
            'event_id' => $out['event']->id,
            'status'   => $out['event']->status,
            'agent'    => 'stocks',
            'file'     => [
                'name'       => $result['name'],
                'rows'       => $result['rows'],
                'flagged'    => $result['flagged'],
                'warehouses' => $result['warehouses'],
                'url'        => "/api/agents/ordres/{$out['event']->id}/fichier",
            ],
        ], 201);
    }

    /** GET /api/agents/ordres/{event}/fichier — la feuille préparée par l'agent. */
    public function file(Request $request, AgentEvent $event): StreamedResponse
    {
        $this->ensureInteractiveUser($request);

        $action = AgentAction::where('event_id', $event->id)->where('action', 'prepare_inventory_sheet')->latest('id')->first();
        $path = $action?->result['path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404, 'Fichier introuvable.');

        return Storage::disk('local')->download($path, $action->result['name']);
    }

    private function ensureInteractiveUser(Request $request): void
    {
        // Jeton de connexion (abilities « * ») ou session : autorisé. Jeton à
        // abilities restreintes (agents IA) : refusé.
        $token = $request->user()?->currentAccessToken();
        abort_if($token && !$token->can('agents:orders'), 403, 'Réservé aux utilisateurs connectés.');
    }
}
