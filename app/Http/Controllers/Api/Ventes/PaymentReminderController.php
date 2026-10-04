<?php

namespace App\Http\Controllers\Api\Ventes;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\PaymentReminder;
use App\Services\Agents\AgentOrderService;
use App\Services\Agents\ReminderSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Écran « Relances de paiement » : le contrôle des encaissements de l'agent
 * Recouvrement et les brouillons de relance, à valider, modifier ou rejeter.
 * Seule la validation (`validateReminder`) peut contacter un client.
 */
class PaymentReminderController extends Controller
{
    public function __construct(private AgentOrderService $orders, private ReminderSender $sender)
    {
    }

    /** GET /api/ventes/relances */
    public function index(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $status = $request->query('status');
        $query = PaymentReminder::with(['document:id,reference,due_at', 'thirdPartner:id,tp_title,tp_phone'])->latest('id');
        if ($status) {
            $query->where('status', $status);
        }

        $last = AgentAction::where('action', 'verify_collections_and_prepare_reminders')->latest('id')->first();

        return response()->json([
            'agent_active' => (bool) Agent::where('domain', 'recouvrement')->value('is_active'),
            'summary'      => [
                'to_validate' => PaymentReminder::whereIn('status', [PaymentReminder::STATUS_DRAFT, PaymentReminder::STATUS_FAILED])->count(),
                'sent'        => PaymentReminder::where('status', PaymentReminder::STATUS_SENT)->count(),
                'amount_due'  => (float) PaymentReminder::whereIn('status', [PaymentReminder::STATUS_DRAFT, PaymentReminder::STATUS_FAILED])->sum('amount_due'),
            ],
            'last_control' => $last ? [
                'at'        => $last->created_at,
                'overdue'   => $last->result['overdue'] ?? 0,
                'created'   => count($last->result['created'] ?? []),
                'anomalies' => $last->result['anomalies'] ?? [],
            ] : null,
            'reminders'    => $query->limit(100)->get()->map(fn (PaymentReminder $r) => $this->present($r))->values(),
        ]);
    }

    /** POST /api/ventes/relances/controle — l'agent contrôle les encaissements et prépare les relances. */
    public function control(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $out = $this->orders->orderCollections($request->user()?->name);
        if (!$out['ok']) {
            return response()->json(['message' => $out['message'], 'event_id' => $out['event']->id], $out['http']);
        }

        $r = $out['result'];

        return response()->json([
            'event_id'  => $out['event']->id,
            'overdue'   => $r['overdue'],
            'created'   => count($r['created']),
            'by_level'  => $r['by_level'],
            'anomalies' => $r['anomalies'],
        ], 201);
    }

    /** PUT /api/ventes/relances/{reminder} — corriger le texte avant validation. */
    public function update(Request $request, PaymentReminder $reminder): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        if (!in_array($reminder->status, [PaymentReminder::STATUS_DRAFT, PaymentReminder::STATUS_FAILED], true)) {
            return response()->json(['message' => 'Cette relance a déjà été traitée.'], 422);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'channel' => ['nullable', 'in:whatsapp,manual'],
        ]);

        $reminder->update(array_filter($data));

        return response()->json($this->present($reminder->fresh(['document', 'thirdPartner'])));
    }

    /** POST /api/ventes/relances/{reminder}/valider — un humain valide : envoi (WhatsApp) ou marquée traitée (manuel). */
    public function validateReminder(Request $request, PaymentReminder $reminder): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $data = $request->validate([
            'channel' => ['nullable', 'in:whatsapp,manual'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $reminder = $this->sender->validate($reminder, $request->user(), $data['channel'] ?? null, $data['message'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Un échec d'envoi est un résultat normal (canal non configuré) : 200 avec le statut « failed ».
        return response()->json($this->present($reminder->load(['document', 'thirdPartner'])));
    }

    /** POST /api/ventes/relances/{reminder}/rejeter */
    public function reject(Request $request, PaymentReminder $reminder): JsonResponse
    {
        $this->ensureInteractiveUser($request);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $reminder = $this->sender->reject($reminder, $request->user(), $data['reason'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->present($reminder->load(['document', 'thirdPartner'])));
    }

    private function present(PaymentReminder $r): array
    {
        return [
            'id'           => $r->id,
            'level'        => $r->level,
            'channel'      => $r->channel,
            'status'       => $r->status,
            'message'      => $r->message,
            'amount_due'   => (float) $r->amount_due,
            'days_overdue' => $r->days_overdue,
            'error'        => $r->error,
            'reason'       => $r->reason,
            'sent_at'      => $r->sent_at,
            'created_at'   => $r->created_at,
            'document'     => $r->document ? ['id' => $r->document->id, 'reference' => $r->document->reference, 'due_at' => $r->document->due_at] : null,
            'client'       => $r->thirdPartner ? ['id' => $r->thirdPartner->id, 'title' => $r->thirdPartner->tp_title, 'phone' => $r->thirdPartner->tp_phone] : null,
        ];
    }

    private function ensureInteractiveUser(Request $request): void
    {
        // Jeton de connexion (abilities « * ») ou session : autorisé. Jeton à
        // abilities restreintes (agents IA) : refusé.
        $token = $request->user()?->currentAccessToken();
        abort_if($token && !$token->can('ventes:reminders'), 403, 'Réservé aux utilisateurs connectés.');
    }
}
