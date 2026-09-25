<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Models\OrderMessage;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Services\Messaging\CustomerChatAuth;
use App\Services\Messaging\InboundOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chat « commande rapide » de la boutique (O3_ecom) : le client s'identifie
 * par un code reçu sur son téléphone, puis tape sa commande en texte libre ;
 * un BL brouillon est créé comme pour un WhatsApp (InboundOrderService).
 */
class EcomChatController extends Controller
{
    public function __construct(
        private CustomerChatAuth $auth,
        private InboundOrderService $inbound,
    ) {
    }

    /** POST /api/ecom/chat/code {phone} */
    public function requestCode(Request $request): JsonResponse
    {
        if ($closed = $this->closedResponse()) {
            return $closed;
        }
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $this->auth->requestCode($data['phone'], $request->ip());

        return response()->json([
            'message' => 'Si ce numéro correspond à un compte client, un code vient de lui être envoyé.',
        ]);
    }

    /** POST /api/ecom/chat/verify {phone, code} */
    public function verify(Request $request): JsonResponse
    {
        if ($closed = $this->closedResponse()) {
            return $closed;
        }
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'code'  => ['required', 'string', 'max:6'],
        ]);

        $session = $this->auth->verify($data['phone'], trim($data['code']));
        if (!$session) {
            return response()->json(['message' => 'Code incorrect ou expiré.'], 422);
        }

        return response()->json([
            'token'    => $session['token'],
            'customer' => ['name' => $session['customer']->tp_title],
        ]);
    }

    /** POST /api/ecom/chat/messages {text} — en-tête X-Chat-Token */
    public function send(Request $request): JsonResponse
    {
        if ($closed = $this->closedResponse()) {
            return $closed;
        }
        $customer = $this->customer($request);
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);

        $result = $this->inbound->process('web_client', $customer->tp_phone, $data['text'], null, null, $customer);

        return response()->json([
            'status'    => $result['status'],
            'reply'     => $result['reply'],
            'reference' => $result['document']['reference'] ?? null,
        ]);
    }

    /** GET /api/ecom/chat/messages — les échanges du client sur ce chat. */
    public function history(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $messages = OrderMessage::where('third_partner_id', $customer->id)
            ->where('channel', 'web_client')
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (OrderMessage $m) => [
                'id'         => $m->id,
                'direction'  => $m->direction,
                'body'       => $m->body,
                'created_at' => $m->created_at,
            ]);

        // Clé « messages » (et non « data ») : l'intercepteur de la boutique déballe « data ».
        return response()->json(['customer' => ['name' => $customer->tp_title], 'messages' => $messages]);
    }

    /** POST /api/ecom/chat/logout */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->header('X-Chat-Token'));
        return response()->json(['message' => 'Déconnecté.']);
    }

    private function customer(Request $request): ThirdPartner
    {
        $customer = $this->auth->customerForToken($request->header('X-Chat-Token'));
        abort_unless($customer, 401, 'Session expirée : demandez un nouveau code.');
        return $customer;
    }

    private function closedResponse(): ?JsonResponse
    {
        return Setting::get('messaging', 'inbound_enabled', 'false') === 'true'
            ? null
            : response()->json(['message' => 'La commande par chat n\'est pas disponible pour le moment.'], 503);
    }
}
