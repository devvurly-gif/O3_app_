<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Services\Messaging\InboundOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /api/webhooks/twilio/inbound — message WhatsApp ou SMS reçu sur un
 * numéro Twilio (« A message comes in »). Signature vérifiée en amont par
 * VerifyTwilioSignature.
 *
 * Twilio attend une réponse en moins de 15 s : on répond tout de suite par un
 * TwiML vide, et le traitement (lecture, IA éventuelle, création du BL,
 * réponse au client via l'API Twilio) se fait juste après l'envoi de la
 * réponse HTTP — même motif que les notifications WhatsApp existantes.
 */
class TwilioInboundController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $from = mb_substr((string) $request->input('From', ''), 0, 60);
        $body = mb_substr((string) $request->input('Body', ''), 0, 10000);
        $sid = mb_substr((string) ($request->input('MessageSid') ?: $request->input('SmsMessageSid', '')), 0, 64);
        $numMedia = (int) $request->input('NumMedia', 0);
        $channel = str_starts_with(strtolower($from), 'whatsapp:') ? 'whatsapp' : 'sms';

        if ($from !== '') {
            dispatch(fn () => app(InboundOrderService::class)
                ->process($channel, $from, $body, $sid !== '' ? $sid : null, null, null, $numMedia)
            )->afterResponse();
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }
}
