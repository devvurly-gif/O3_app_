<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class WhatsAppService
{
    private ?Client $client = null;

    private function getClient(): ?Client
    {
        if ($this->client) {
            return $this->client;
        }

        // Read from DB settings first, fallback to config/env
        $sid   = Setting::get('whatsapp', 'twilio_sid') ?: config('twilio.sid');
        $token = Setting::get('whatsapp', 'twilio_auth_token') ?: config('twilio.auth_token');
        $enabled = Setting::get('whatsapp', 'whatsapp_enabled', 'true');

        if ($enabled === 'false') {
            Log::info('WhatsApp: disabled in settings.');
            return null;
        }

        if (!$sid || !$token || $sid === 'your_account_sid') {
            return null;
        }

        $this->client = new Client($sid, $token);

        return $this->client;
    }

    /**
     * Send a WhatsApp message via Twilio.
     */
    public function send(string $to, string $message): bool
    {
        $client = $this->getClient();
        if (!$client) return false;

        // Read from DB settings first, fallback to config
        $from = Setting::get('whatsapp', 'twilio_whatsapp_from') ?: config('twilio.whatsapp_from');
        if (!str_starts_with($from, 'whatsapp:')) {
            $from = "whatsapp:" . $from;
        }

        $toClean = $this->formatWhatsAppNumber($to);
        $toWhatsApp = str_starts_with($toClean, 'whatsapp:') ? $toClean : "whatsapp:" . $toClean;

        try {
            $client->messages->create($toWhatsApp, [
                'from' => $from,
                'body' => $message,
            ]);
            Log::info("WhatsApp message sent to {$toWhatsApp}");
            return true;
        } catch (\Throwable $e) {
            Log::error("Twilio Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Format phone number for WhatsApp (whatsapp:+212XXXXXXXXX).
     */
    private function formatWhatsAppNumber(string $phone): string
    {
        if (str_starts_with($phone, 'whatsapp:')) {
            return $phone;
        }

        $phone = preg_replace('/[\s\-\.]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '+212' . substr($phone, 1);
        }

        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return 'whatsapp:' . $phone;
    }
}
