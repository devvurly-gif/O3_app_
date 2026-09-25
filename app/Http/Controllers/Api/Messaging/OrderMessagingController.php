<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\OrderMessage;
use App\Models\ThirdPartner;
use App\Services\Messaging\InboundOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Page « Messagerie commandes » de l'équipe : saisir un BL en texte libre,
 * et suivre toutes les conversations (WhatsApp, SMS, chats).
 */
class OrderMessagingController extends Controller
{
    public function __construct(private InboundOrderService $inbound)
    {
    }

    /** POST /api/messagerie/commandes */
    public function send(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $data = $request->validate([
            'third_partner_id' => ['nullable', 'integer', 'exists:third_partners,id'],
            'text'             => ['required', 'string', 'max:4000'],
        ]);

        $customer = null;
        if (!empty($data['third_partner_id'])) {
            $customer = ThirdPartner::whereIn('tp_Role', ['customer', 'both'])->find($data['third_partner_id']);
            if (!$customer) {
                return response()->json(['message' => "Ce tiers n'est pas un client."], 422);
            }
        }

        $result = $this->inbound->process('web_staff', null, $data['text'], null, $request->user(), $customer);

        return response()->json($result, $result['status'] === 'created' ? 201 : 200);
    }

    /** GET /api/messagerie/conversations — une ligne par client / numéro, la plus récente d'abord. */
    public function conversations(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $channel = $request->query('channel');

        $conversations = OrderMessage::query()
            ->with(['thirdPartner:id,tp_title,tp_code', 'user:id,name'])
            ->when($channel, fn ($q) => $q->where('channel', $channel))
            ->latest('id')
            ->limit(1000)
            ->get()
            ->groupBy(fn (OrderMessage $m) => $this->conversationKey($m))
            ->map(function ($messages, $key) {
                /** @var OrderMessage $last */
                $last = $messages->first();
                $customer = $messages->first(fn ($m) => $m->thirdPartner)?->thirdPartner;
                return [
                    'key'       => $key,
                    'title'     => $customer?->tp_title ?? $last->phone ?? $last->user?->name ?? 'Conversation',
                    'subtitle'  => $customer?->tp_code ?? $last->phone,
                    'channels'  => $messages->pluck('channel')->unique()->values(),
                    'last_body' => mb_substr($last->body, 0, 120),
                    'last_at'   => $last->created_at,
                    'last_status' => $messages->first(fn ($m) => $m->direction === 'in')?->status,
                ];
            })
            ->take(100)
            ->values();

        return response()->json(['data' => $conversations]);
    }

    /** GET /api/messagerie/fil?key=c12 | p+2126… | u3 */
    public function thread(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $key = (string) $request->query('key', '');
        if (!preg_match('/^([cpu])(.+)$/', $key, $m)) {
            return response()->json(['message' => 'Conversation inconnue.'], 422);
        }

        $query = OrderMessage::query()->with(['user:id,name', 'document:id,reference,status']);
        match ($m[1]) {
            'c' => $query->where('third_partner_id', (int) $m[2]),
            'p' => $query->whereNull('third_partner_id')->where('phone', $m[2]),
            'u' => $query->whereNull('third_partner_id')->whereNull('phone')->where('user_id', (int) $m[2]),
        };

        $messages = $query->latest('id')->limit(200)->get()->reverse()->values()->map(fn (OrderMessage $msg) => [
            'id'        => $msg->id,
            'channel'   => $msg->channel,
            'direction' => $msg->direction,
            'body'      => $msg->body,
            'status'    => $msg->status,
            'author'    => $msg->direction === 'in' ? ($msg->user?->name ?? $msg->phone) : null,
            'document'  => $msg->document ? [
                'id'        => $msg->document->id,
                'reference' => $msg->document->reference,
                'status'    => $msg->document->status,
            ] : null,
            'parse_method' => $msg->parse_method,
            'created_at'   => $msg->created_at,
        ]);

        return response()->json(['data' => $messages]);
    }

    private function conversationKey(OrderMessage $m): string
    {
        if ($m->third_partner_id) {
            return 'c' . $m->third_partner_id;
        }
        return $m->phone ? 'p' . $m->phone : 'u' . $m->user_id;
    }

    /**
     * Réservé aux sessions ouvertes par un humain (jeton de connexion, toutes
     * abilities). Les jetons restreints des agents IA — même avec le rôle
     * caissier — n'y ont pas accès.
     */
    private function ensureInteractiveUser(Request $request): void
    {
        // Jeton de connexion (abilities « * ») ou session : autorisé. Jeton à
        // abilities restreintes (agents IA) : refusé.
        $token = $request->user()?->currentAccessToken();
        abort_if($token && !$token->can('messagerie:chat'), 403, 'Réservé aux utilisateurs connectés.');
    }
}
