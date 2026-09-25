<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Messaging\InboundOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/webhooks/infobip/inbound/{secret} — SMS (format MO_JSON_2,
 * « Forward to HTTP » sur le numéro) ou WhatsApp reçus via Infobip.
 *
 * Infobip ne signe pas les renvois d'un numéro comme Twilio : l'adresse
 * contient un secret aléatoire (whatsapp.infobip_webhook_secret), comparé en
 * temps constant. Mauvais secret ou pas de tenant → 404, sans rien révéler.
 *
 * Même principe que TwilioInboundController : réponse immédiate, traitement
 * juste après l'envoi de la réponse HTTP.
 */
class InfobipInboundController extends Controller
{
    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $expected = (string) Setting::get('whatsapp', 'infobip_webhook_secret', '');
        abort_unless(function_exists('tenant') && tenant() && $expected !== '' && hash_equals($expected, $secret), 404);

        $messages = [];
        foreach (array_slice((array) $request->input('results', []), 0, 50) as $r) {
            if (!is_array($r) || empty($r['from'])) {
                continue;
            }

            $isWhatsApp = strtoupper((string) ($r['integrationType'] ?? '')) === 'WHATSAPP';
            if ($isWhatsApp) {
                $type = strtoupper((string) ($r['message']['type'] ?? 'TEXT'));
                $text = $type === 'TEXT' ? (string) ($r['message']['text'] ?? '') : '';
                $numMedia = $type === 'TEXT' ? 0 : 1;
            } else {
                $text = (string) ($r['text'] ?? '');
                $numMedia = 0;
            }

            $messages[] = [
                'channel'  => $isWhatsApp ? 'whatsapp' : 'sms',
                'from'     => mb_substr((string) $r['from'], 0, 60),
                'body'     => mb_substr($text, 0, 10000),
                'id'       => isset($r['messageId']) ? 'IB-' . mb_substr((string) $r['messageId'], 0, 60) : null,
                'numMedia' => $numMedia,
            ];
        }

        if ($messages !== []) {
            dispatch(function () use ($messages) {
                $service = app(InboundOrderService::class);
                foreach ($messages as $m) {
                    $service->process($m['channel'], $m['from'], $m['body'], $m['id'], null, null, $m['numMedia']);
                }
            })->afterResponse();
        }

        return response()->json(['received' => count($messages)]);
    }
}
