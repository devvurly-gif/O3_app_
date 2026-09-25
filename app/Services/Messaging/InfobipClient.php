<?php

namespace App\Services\Messaging;

use App\Models\Setting;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de SMS et de WhatsApp via l'API Infobip, fournisseur alternatif à
 * Twilio (réglage « whatsapp.provider »). Même contrat que WhatsAppService /
 * SmsService : renvoie true/false, ne lève jamais d'exception.
 *
 *   SMS      : POST {base}/sms/3/messages
 *   WhatsApp : POST {base}/whatsapp/1/message/text (texte libre : seulement dans
 *              les 24 h qui suivent un message du client)
 *   Auth     : en-tête « Authorization: App <clé API> »
 */
class InfobipClient
{
    public function sendSms(string $to, string $text): bool
    {
        $sender = Setting::get('messaging', 'sms_from');
        if (!$sender) {
            Log::info('Infobip SMS : expéditeur SMS non configuré.');
            return false;
        }

        return $this->post('/sms/3/messages', [
            'messages' => [[
                'sender'       => $sender,
                'destinations' => [['to' => $this->digits($to)]],
                'content'      => ['text' => $text],
            ]],
        ], 'SMS');
    }

    public function sendWhatsApp(string $to, string $text): bool
    {
        $from = Setting::get('whatsapp', 'infobip_whatsapp_from');
        if (!$from) {
            Log::info('Infobip WhatsApp : numéro expéditeur non configuré.');
            return false;
        }

        return $this->post('/whatsapp/1/message/text', [
            'from'    => $this->digits($from),
            'to'      => $this->digits($to),
            'content' => ['text' => $text],
        ], 'WhatsApp');
    }

    private function post(string $path, array $body, string $label): bool
    {
        $base = $this->baseUrl();
        $key = $this->apiKey();
        if (!$base || !$key) {
            Log::info("Infobip {$label} : adresse d'API ou clé API non configurée.");
            return false;
        }

        try {
            $response = Http::withHeaders(['Authorization' => 'App ' . $key])
                ->acceptJson()
                ->timeout(10)
                ->post($base . $path, $body);

            if (!$response->successful()) {
                Log::error("Infobip {$label} : réponse {$response->status()} — " . mb_substr($response->body(), 0, 300));
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::error("Infobip {$label} : " . $e->getMessage());
            return false;
        }
    }

    /** « xxxxx.api.infobip.com » ou « https://xxxxx.api.infobip.com/ » → « https://xxxxx.api.infobip.com ». */
    private function baseUrl(): ?string
    {
        $base = trim((string) Setting::get('whatsapp', 'infobip_base_url'));
        if ($base === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $base)) {
            $base = 'https://' . $base;
        }
        return rtrim($base, '/');
    }

    /** Clé stockée chiffrée (SettingController::SECRET_SETTINGS). */
    private function apiKey(): ?string
    {
        $stored = Setting::get('whatsapp', 'infobip_api_key');
        if (!$stored) {
            return null;
        }
        try {
            return decrypt($stored);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Infobip attend le format international sans « + » : 212620696967. */
    private function digits(string $phone): string
    {
        return ltrim(PhoneNumber::normalize($phone) ?? $phone, '+');
    }
}
