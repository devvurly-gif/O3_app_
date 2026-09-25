<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Messaging\InfobipClient;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/**
 * Envoi de SMS classiques via le fournisseur choisi (whatsapp.provider) :
 * Twilio, avec les mêmes identifiants que WhatsAppService, ou Infobip
 * (InfobipClient). L'expéditeur SMS est commun : messaging.sms_from.
 */
class SmsService
{
    public function send(string $to, string $message): bool
    {
        if (Setting::get('whatsapp', 'provider', 'twilio') === 'infobip') {
            return app(InfobipClient::class)->sendSms($to, $message);
        }

        $sid   = Setting::get('whatsapp', 'twilio_sid') ?: config('twilio.sid');
        $token = Setting::get('whatsapp', 'twilio_auth_token') ?: config('twilio.auth_token');
        $from  = Setting::get('messaging', 'sms_from');

        if (!$sid || !$token || !$from || $sid === 'your_account_sid') {
            Log::info('SMS : Twilio ou numéro expéditeur SMS non configuré.');
            return false;
        }

        try {
            (new Client($sid, $token))->messages->create(PhoneNumber::normalize($to) ?? $to, [
                'from' => $from,
                'body' => $message,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Twilio SMS : ' . $e->getMessage());
            return false;
        }
    }
}
