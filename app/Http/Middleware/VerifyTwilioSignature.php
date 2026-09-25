<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

/**
 * N'accepte qu'une requête réellement envoyée par Twilio : l'en-tête
 * X-Twilio-Signature est un HMAC de l'URL et des paramètres, calculé avec le
 * jeton d'authentification Twilio du tenant. Sans lui, n'importe qui pourrait
 * se faire passer pour un client et créer des BL à son nom.
 *
 * L'URL est vérifiée en https d'abord : derrière un proxy non déclaré
 * (TrustProxies n'en liste aucun), Laravel peut voir la requête en http alors
 * que Twilio a signé l'adresse https configurée dans sa console.
 */
class VerifyTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // api.php est aussi monté sur le domaine central : pas de tenant, pas de webhook.
        abort_unless(function_exists('tenant') && tenant(), 404);

        $token = Setting::get('whatsapp', 'twilio_auth_token') ?: config('twilio.auth_token');
        $signature = (string) $request->header('X-Twilio-Signature', '');

        abort_if(!$token || $signature === '', 403, 'Signature Twilio absente.');

        $validator = new RequestValidator($token);
        $params = $request->request->all();
        $urls = array_unique([
            'https://' . $request->getHttpHost() . $request->getRequestUri(),
            $request->fullUrl(),
        ]);

        foreach ($urls as $url) {
            if ($validator->validate($signature, $url, $params)) {
                return $next($request);
            }
        }

        abort(403, 'Signature Twilio invalide.');
    }
}
