<?php

namespace App\Http\Controllers\Api\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentAction;
use App\Services\Agents\AgentRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion directe d'un agent depuis sa carte (sans passer par l'orchestrateur) : l'activer ou le désactiver,
 * lui créer son compte utilisateur « Agent IA ». Réservé aux utilisateurs qui gèrent les réglages ; chaque
 * action est inscrite au journal des agents.
 */
class AgentManagementController extends Controller
{
    public function __construct(private readonly AgentRegistry $registry)
    {
    }

    /** PUT /api/agents/{agent}/etat */
    public function toggle(Request $request, Agent $agent): JsonResponse
    {
        $this->denyAgentTokens($request);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        $agent->update(['is_active' => (bool) $data['is_active']]);
        $this->registry->syncAccountState($agent);   // un agent désactivé ne laisse pas de compte actif
        $this->log($agent, $agent->is_active ? 'agent_activated' : 'agent_deactivated', $request);

        return response()->json(['domain' => $agent->domain, 'is_active' => $agent->is_active]);
    }

    /** POST /api/agents/{agent}/compte : crée le compte de l'agent s'il n'en a pas (idempotent, aucun jeton émis). */
    public function createAccount(Request $request, Agent $agent): JsonResponse
    {
        $this->denyAgentTokens($request);
        $existed = (bool) $agent->user_id;

        $user = $this->registry->ensureAccount($agent);
        abort_if($user === null, 422, "Le compte n'a pas pu être créé.");
        $existed || $this->log($agent, 'agent_account_created', $request, ['email' => $user->email]);

        return response()->json(['domain' => $agent->domain, 'account' => $user->name, 'created' => !$existed], $existed ? 200 : 201);
    }

    /** Jeton de connexion (abilities « * ») ou session : autorisé. Jeton à abilities restreintes (agents IA) : refusé. */
    private function denyAgentTokens(Request $request): void
    {
        $token = $request->user()?->currentAccessToken();
        abort_if($token && !$token->can('agents:activity'), 403, 'Réservé aux utilisateurs connectés.');
    }

    /** @param array<string, mixed> $extra */
    private function log(Agent $agent, string $action, Request $request, array $extra = []): void
    {
        AgentAction::create([
            'agent_id' => $agent->id,
            'event_id' => null,
            'action'   => $action,
            'level'    => 'approval',
            'input'    => null,
            'result'   => array_merge(['by' => $request->user()?->name], $extra),
        ]);
    }
}
