<?php

namespace App\Services\Messaging;

use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Services\SmsService;
use App\Services\Ventes\CustomerLookup;
use App\Services\WhatsAppService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Identification d'un client sur le chat « commande rapide » de la boutique.
 *
 * La clé API de la boutique est lisible dans son code JavaScript : elle ne
 * prouve rien sur la personne. Seul un code envoyé au numéro de la fiche
 * client prouve que c'est bien ce client qui commande.
 *
 *   - demande de code : réponse identique que le numéro soit connu ou non
 *     (pas d'énumération des clients) ; au plus 3 codes / 10 min par numéro ;
 *   - code : 6 chiffres, haché, valable 10 min, 5 essais ;
 *   - session : jeton aléatoire haché en base, valable 24 h.
 */
class CustomerChatAuth
{
    private const CODE_TTL_MINUTES = 10;
    private const MAX_CODES_PER_WINDOW = 3;
    private const MAX_ATTEMPTS = 5;
    private const SESSION_TTL_HOURS = 24;

    public function __construct(
        private CustomerLookup $customers,
        private SmsService $sms,
        private WhatsAppService $whatsapp,
        private OrderReplyFormatter $replies,
    ) {
    }

    /** Envoie un code si le numéro correspond à exactement un client. Ne révèle jamais le résultat. */
    public function requestCode(string $phone, ?string $ip): void
    {
        $normalized = PhoneNumber::normalize($phone);
        if (!$normalized) {
            return;
        }

        $recent = DB::table('customer_chat_codes')
            ->where('phone', $normalized)
            ->where('created_at', '>=', now()->subMinutes(self::CODE_TTL_MINUTES))
            ->count();
        if ($recent >= self::MAX_CODES_PER_WINDOW) {
            return;
        }

        $found = $this->customers->byPhone($normalized);
        if ($found->count() !== 1) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('customer_chat_codes')->insert([
            'phone'            => $normalized,
            'third_partner_id' => $found->first()->id,
            'code_hash'        => Hash::make($code),
            'expires_at'       => now()->addMinutes(self::CODE_TTL_MINUTES),
            'ip'               => $ip,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $text = "Votre code de commande : {$code} (valable " . self::CODE_TTL_MINUTES . " min). Ne le communiquez à personne.";
        // SMS d'abord : un WhatsApp envoyé hors fenêtre de 24 h exige un modèle approuvé.
        if (!Setting::get('messaging', 'sms_from') || !$this->sms->send($normalized, $text)) {
            $this->whatsapp->send($normalized, $text);
        }
    }

    /** @return array{token: string, customer: ThirdPartner}|null */
    public function verify(string $phone, string $code): ?array
    {
        $normalized = PhoneNumber::normalize($phone);
        if (!$normalized || !preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $row = DB::table('customer_chat_codes')
            ->where('phone', $normalized)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
        if (!$row || $row->attempts >= self::MAX_ATTEMPTS) {
            return null;
        }

        DB::table('customer_chat_codes')->where('id', $row->id)->increment('attempts');
        if (!Hash::check($code, $row->code_hash)) {
            return null;
        }
        DB::table('customer_chat_codes')->where('id', $row->id)->update(['consumed_at' => now(), 'updated_at' => now()]);

        $customer = ThirdPartner::whereIn('tp_Role', ['customer', 'both'])->find($row->third_partner_id);
        if (!$customer) {
            return null;
        }

        $token = Str::random(64);
        DB::table('customer_chat_sessions')->insert([
            'third_partner_id' => $customer->id,
            'token_hash'       => hash('sha256', $token),
            'expires_at'       => now()->addHours(self::SESSION_TTL_HOURS),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return ['token' => $token, 'customer' => $customer];
    }

    public function customerForToken(?string $token): ?ThirdPartner
    {
        if (!$token || strlen($token) !== 64) {
            return null;
        }
        $session = DB::table('customer_chat_sessions')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
        if (!$session) {
            return null;
        }
        DB::table('customer_chat_sessions')->where('id', $session->id)->update(['last_used_at' => now()]);

        return ThirdPartner::whereIn('tp_Role', ['customer', 'both'])->find($session->third_partner_id);
    }

    public function logout(?string $token): void
    {
        if ($token) {
            DB::table('customer_chat_sessions')->where('token_hash', hash('sha256', $token))->delete();
        }
    }
}
