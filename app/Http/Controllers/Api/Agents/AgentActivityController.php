<?php

namespace App\Http\Controllers\Api\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentCase;
use App\Models\AgentEvent;
use App\Models\DocumentHeader;
use App\Models\ThirdPartner;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Écran « Activité des agents » : ce que le routeur a reçu et à qui il l'a confié.
 * Lecture seule ; réservé aux utilisateurs qui gèrent les réglages.
 */
class AgentActivityController extends Controller
{
    private const TEXT_PREVIEW = 160;

    /** GET /api/agents/activite */
    public function index(Request $request): JsonResponse
    {
        // Jeton de connexion (abilities « * ») ou session : autorisé. Jeton à
        // abilities restreintes (agents IA) : refusé.
        $token = $request->user()?->currentAccessToken();
        abort_if($token && !$token->can('agents:activity'), 403, 'Réservé aux utilisateurs connectés.');

        $filters = $request->validate([
            'status'   => ['nullable', 'string', 'max:20'],
            'agent'    => ['nullable', 'string', 'max:30'],
            'source'   => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AgentEvent::query()->with(['agent:id,domain,name']);
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (!empty($filters['agent'])) {
            $query->whereHas('agent', fn ($q) => $q->where('domain', $filters['agent']));
        }

        $page = $query->latest('id')->paginate((int) ($filters['per_page'] ?? 25));

        // Libellés en lot : clients des dossiers et références de documents.
        $caseIds = $page->getCollection()->pluck('case_id')->filter()->unique();
        $cases = AgentCase::whereIn('id', $caseIds)->get()->keyBy('id');
        $partnerIds = $page->getCollection()
            ->map(fn (AgentEvent $e) => $e->entities['third_partner_id'] ?? $cases->get($e->case_id)?->third_partner_id)
            ->filter()->unique();
        $partners = ThirdPartner::whereIn('id', $partnerIds)->get(['id', 'tp_title', 'tp_code'])->keyBy('id');
        $docIds = $page->getCollection()->map(fn (AgentEvent $e) => $e->entities['document_id'] ?? null)->filter()->unique();
        $documents = DocumentHeader::whereIn('id', $docIds)->get(['id', 'reference', 'status', 'document_type'])->keyBy('id');

        $events = $page->getCollection()->map(function (AgentEvent $e) use ($cases, $partners, $documents) {
            $partnerId = $e->entities['third_partner_id'] ?? $cases->get($e->case_id)?->third_partner_id;
            $partner = $partnerId ? $partners->get($partnerId) : null;
            $doc = $documents->get($e->entities['document_id'] ?? 0);

            return [
                'id'          => $e->id,
                'created_at'  => $e->created_at,
                'source'      => $e->source,
                'type'        => $e->type,
                'status'      => $e->status,
                'priority'    => $e->priority,
                'agent'       => $e->agent ? ['domain' => $e->agent->domain, 'name' => $e->agent->name] : null,
                'case_id'     => $e->case_id,
                'client'      => $partner ? ['id' => $partner->id, 'title' => $partner->tp_title, 'code' => $partner->tp_code] : null,
                'phone'       => $e->entities['phone'] ?? null,
                'text'        => isset($e->payload['text']) ? mb_substr($e->payload['text'], 0, self::TEXT_PREVIEW) : null,
                'reason'      => $e->payload['legacy_reason'] ?? null,
                'document'    => $doc ? ['id' => $doc->id, 'reference' => $doc->reference, 'status' => $doc->status, 'type' => $doc->document_type] : null,
            ];
        })->values();

        return response()->json([
            'summary' => [
                'total'      => AgentEvent::count(),
                'last_24h'   => AgentEvent::where('created_at', '>=', now()->subDay())->count(),
                'by_status'  => AgentEvent::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
                'open_cases' => AgentCase::where('status', 'open')->count(),
                'router_on'  => \App\Models\Setting::get('agents', 'router_enabled', 'false') === 'true',
            ],
            'agents'  => $this->agents(),
            'events'  => $events,
            'meta'    => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    /** Un agent par domaine : actif ou non, compte rattaché, volume et dernière activité. */
    private function agents(): array
    {
        $counts = AgentEvent::selectRaw('agent_id, count(*) as n, max(created_at) as last_at')->groupBy('agent_id')->get()->keyBy('agent_id');
        $users = User::whereIn('id', Agent::whereNotNull('user_id')->pluck('user_id'))->get(['id', 'name', 'email'])->keyBy('id');

        return Agent::orderBy('id')->get()->map(fn (Agent $a) => [
            'domain'       => $a->domain,
            'name'         => $a->name,
            'is_active'    => $a->is_active,
            'account'      => $a->user_id ? ($users->get($a->user_id)?->name) : null,
            'ability'      => $a->ability,
            'events_count' => (int) ($counts->get($a->id)?->n ?? 0),
            'last_event_at' => $counts->get($a->id)?->last_at,
        ])->all();
    }
}
