<?php

namespace App\Support;

/**
 * Forme canonique d'un numéro de téléphone marocain, pour comparer ce qui
 * arrive de Twilio (« whatsapp:+212612345678 », « +212612345678 ») à ce qui
 * est saisi sur une fiche (« 06 12 34 56 78 », « 0612-345678 »…).
 *
 *   0XXXXXXXXX / 00212XXXXXXXXX / 212XXXXXXXXX / +212XXXXXXXXX → +212XXXXXXXXX
 *
 * Un numéro étranger (+33…) est conservé tel quel, ramené à « +chiffres ».
 */
final class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = preg_replace('/^whatsapp:/i', '', trim($phone));
        $plus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (!$plus && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $plus = true;
        }

        if (!$plus && str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+212' . substr($digits, 1);
        }

        if (!$plus && str_starts_with($digits, '212') && strlen($digits) === 12) {
            return '+' . $digits;
        }

        return '+' . $digits;
    }

    /** Les 9 derniers chiffres : sert de présélection SQL avant la comparaison exacte. */
    public static function tail(?string $phone, int $length = 9): ?string
    {
        $normalized = self::normalize($phone);
        if ($normalized === null) {
            return null;
        }
        $digits = ltrim($normalized, '+');
        return strlen($digits) >= $length ? substr($digits, -$length) : null;
    }

    public static function equals(?string $a, ?string $b): bool
    {
        $na = self::normalize($a);
        return $na !== null && $na === self::normalize($b);
    }

    /** Un texte ressemble-t-il à un numéro (au moins 8 chiffres, rien d'autre que séparateurs) ? */
    public static function looksLikePhone(string $text): bool
    {
        return (bool) preg_match('/^\+?[\d\s.\-()]{8,}$/', trim($text))
            && strlen(preg_replace('/\D/', '', $text)) >= 8;
    }
}
