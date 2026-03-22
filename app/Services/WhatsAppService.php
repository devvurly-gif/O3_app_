<?php

namespace App\Services;

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

        $sid   = config('twilio.sid');
        $token = config('twilio.auth_token');

        if (!$sid || !$token || $sid === 'your_account_sid') {
            return null;
        }

        $this->client = new Client($sid, $token);

        return $this->client;
    }

    /**
     * Send a WhatsApp message via Twilio.
     */
    // public function send(string $to, string $message): bool
    // {
    //     $client = $this->getClient();
    //     if (!$client) {
    //         Log::info('WhatsApp: Twilio not configured, skipping message.');
    //         return false;
    //     }

    //     $from = config('twilio.whatsapp_from', 'whatsapp:0014155238886');
    //     $to   = $this->formatWhatsAppNumber($to);

    //     try {
    //         $client->messages->create($to, [
    //             'from' => $from,
    //             'body' => $message,
    //         ]);

    //         Log::info("WhatsApp message sent to {$to}");
    //         return true;
    //     } catch (\Throwable $e) {
    //         Log::warning("WhatsApp send failed to {$to}: {$e->getMessage()}");
    //         return false;
    //     }
    // }

 public function send(string $to, string $message): bool 
{ 
    $client = $this->getClient(); 
    if (!$client) return false;

    // 1. Récupérer l'expéditeur et forcer le préfixe
    $from = config('twilio.whatsapp_from');
    if (!str_starts_with($from, 'whatsapp:')) {
        $from = "whatsapp:" . $from;
    }

    // 2. Formater le destinataire (ex: +33612345678)
    $toClean = $this->formatWhatsAppNumber($to);
    
    // 3. Forcer le préfixe 'whatsapp:' pour le destinataire
    $toWhatsApp = str_starts_with($toClean, 'whatsapp:') ? $toClean : "whatsapp:" . $toClean;

    try { 
        $client->messages->create($toWhatsApp, [ 
            'from' => $from, 
            'body' => $message, 
        ]); 
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
        // Already formatted
        if (str_starts_with($phone, 'whatsapp:')) {
            return $phone;
        }

        // Remove spaces, dashes, dots
        $phone = preg_replace('/[\s\-\.]/', '', $phone);

        // If starts with 0, assume Morocco (+212)
        if (str_starts_with($phone, '0')) {
            $phone = '+212' . substr($phone, 1);
        }

        // Ensure + prefix
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return 'whatsapp:' . $phone;
    }
}
