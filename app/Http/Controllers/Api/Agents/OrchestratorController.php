<?php

namespace App\Http\Controllers\Api\Agents;

use App\Http\Controllers\Controller;
use App\Models\OrchestratorMessage;
use App\Services\Agents\Orchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Session de discussion administrateur ↔ orchestrateur. Chaque administrateur ne
 * voit que sa propre conversation.
 */
class OrchestratorController extends Controller
{
    private const HISTORY = 60;

    public function __construct(private Orchestrator $orchestrator)
    {
    }

    /** GET /api/agents/orchestrateur — l'historique de la session de l'administrateur. */
    public function index(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $messages = OrchestratorMessage::where('user_id', $request->user()->id)
            ->latest('id')->limit(self::HISTORY)->get()->reverse()->values();

        return response()->json(['messages' => $messages->map(fn (OrchestratorMessage $m) => $this->present($m))]);
    }

    /** POST /api/agents/orchestrateur — l'administrateur écrit, l'orchestrateur répond. */
    public function send(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $data = $request->validate(['message' => ['required', 'string', 'min:1', 'max:1000']]);

        $exchange = $this->orchestrator->converse($request->user(), $data['message']);

        return response()->json([
            'user'  => $this->present($exchange['user']),
            'reply' => $this->present($exchange['reply']),
        ], 201);
    }

    /** DELETE /api/agents/orchestrateur — efface l'historique de la session (pas les ordres déjà donnés). */
    public function clear(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        OrchestratorMessage::where('user_id', $request->user()->id)->delete();

        return response()->json(null, 204);
    }

    private function present(OrchestratorMessage $m): array
    {
        return [
            'id'         => $m->id,
            'role'       => $m->role,
            'body'       => $m->body,
            'links'      => $m->meta['links'] ?? [],
            'error'      => (bool) ($m->meta['error'] ?? false),
            'event_id'   => $m->meta['event_id'] ?? null,
            'created_at' => $m->created_at,
        ];
    }

    private function ensureInteractiveUser(Request $request): void
    {
        // Jeton de connexion (abilities « * ») ou session : autorisé. Jeton à
        // abilities restreintes (agents IA) : refusé.
        $token = $request->user()?->currentAccessToken();
        abort_if($token && !$token->can('agents:orchestrator'), 403, 'Réservé aux utilisateurs connectés.');
    }
}
