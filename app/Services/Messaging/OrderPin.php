<?php

namespace App\Services\Messaging;

use App\Models\ThirdPartner;
use Illuminate\Support\Facades\Hash;

/**
 * PIN de commande par message (WhatsApp / SMS).
 *
 * Avoir accès au WhatsApp d'un client ne suffit pas pour commander à sa place :
 * la 1re ligne du message doit porter « PIN 1234 », le PIN à 4 chiffres tiré
 * par O3 à la création de la fiche et remis au client par l'équipe.
 *
 *   - seul le haché est stocké ; le PIN en clair n'est montré qu'une fois ;
 *   - 5 PIN faux de suite → canal bloqué jusqu'à ce que l'équipe génère un
 *     nouveau PIN ;
 *   - le PIN n'est jamais conservé dans l'historique des messages.
 */
class OrderPin
{
    public const MAX_FAILURES = 5;

    /** « PIN 1234 », « pin: 1234 », « PIN-1234 » : une ligne entière. */
    private const LINE = '/^\s*PIN\s*[:\-]?\s*(\d{1,12})\s*$/iu';

    /** Tire un nouveau PIN, remet le compteur à zéro et débloque. Renvoie le PIN en clair. */
    public function generate(ThirdPartner $customer): string
    {
        $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $customer->forceFill([
            'order_pin_hash'      => Hash::make($pin),
            'order_pin_set_at'    => now(),
            'order_pin_failures'  => 0,
            'order_pin_locked_at' => null,
        ])->saveQuietly();

        return $pin;
    }

    /**
     * Sépare le PIN (1re ligne non vide) du reste de la commande.
     *
     * @return array{0: ?string, 1: string} [pin ou null, texte sans la ligne PIN]
     */
    public function extract(string $body): array
    {
        $lines = preg_split('/\R/u', $body);
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match(self::LINE, $line, $m)) {
                unset($lines[$i]);
                return [$m[1], trim(implode("\n", $lines))];
            }
            break;
        }
        return [null, $body];
    }

    /** Masque toute ligne « PIN … » avant d'enregistrer le message. */
    public function redact(string $body): string
    {
        return preg_replace('/^(\s*PIN\s*[:\-]?\s*)\d{1,12}(\s*)$/imu', '$1****$2', $body);
    }

    /**
     * Vérifie le PIN donné. Tient le compteur d'erreurs et bloque au 5e échec.
     *
     * @return string ok | missing | wrong | locked | none
     */
    public function check(ThirdPartner $customer, ?string $pin): string
    {
        if ($customer->order_pin_locked_at !== null) {
            return 'locked';
        }
        if (!$customer->order_pin_hash) {
            return 'none';
        }
        if ($pin === null) {
            return 'missing';
        }

        if (strlen($pin) === 4 && Hash::check($pin, $customer->order_pin_hash)) {
            if ($customer->order_pin_failures > 0) {
                $customer->forceFill(['order_pin_failures' => 0])->saveQuietly();
            }
            return 'ok';
        }

        $failures = $customer->order_pin_failures + 1;
        $customer->forceFill([
            'order_pin_failures'  => $failures,
            'order_pin_locked_at' => $failures >= self::MAX_FAILURES ? now() : null,
        ])->saveQuietly();

        return $failures >= self::MAX_FAILURES ? 'locked' : 'wrong';
    }

    public function remainingAttempts(ThirdPartner $customer): int
    {
        return max(0, self::MAX_FAILURES - (int) $customer->order_pin_failures);
    }
}
