<?php

namespace App\Services\Agents;

/**
 * Codes-barres EAN13 à usage INTERNE (caisse, étiquettes) : jamais un code de fournisseur ou de fabricant.
 *
 * Les préfixes 20 à 29 sont réservés par GS1 à la circulation restreinte (un commerce les attribue à ses
 * propres articles, ils n'entrent en conflit avec aucun code officiel). Le code est « 29 » + l'identifiant de
 * la fiche sur 10 chiffres + la clé de contrôle : déterministe (le même produit donne toujours le même code)
 * et unique tant que les identifiants le sont.
 */
class InternalEan13
{
    public const PREFIX = '29';

    public static function forProduct(int $productId): string
    {
        $base = self::PREFIX . str_pad((string) $productId, 10, '0', STR_PAD_LEFT);

        return $base . self::checkDigit($base);
    }

    /** Clé de contrôle des 12 premiers chiffres. */
    public static function checkDigit(string $twelve): int
    {
        $sum = 0;
        foreach (str_split($twelve) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }

    public static function isValid(string $code): bool
    {
        return preg_match('/^\d{13}$/', $code) === 1 && (int) $code[12] === self::checkDigit(substr($code, 0, 12));
    }
}
