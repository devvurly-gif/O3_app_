<?php

namespace App\Services;

use App\Models\Setting;

class TaxService
{
    public static function isTaxActive(): bool
    {
        return Setting::get('invoice', 'tax_enabled', 'true') === 'true';
    }

    /**
     * Taux de TVA réellement applicable : celui du produit quand l'option TVA est active, 0 sinon.
     * À utiliser partout où un taux enregistré (fiche produit, ligne soumise par le client) entrerait
     * dans un calcul : l'option désactivée ne doit jamais laisser passer le taux de la fiche.
     */
    public static function effectiveRate(float|int|string|null $rate): float
    {
        return self::isTaxActive() ? (float) $rate : 0.0;
    }

    public static function getDefaultTaxRate(): float
    {
        $rate = Setting::get('general', 'default_tax_rate', '20');
        return (float) $rate;
    }

    public static function calculateTax(float $htPrice, float $taxRate): float
    {
        if (!self::isTaxActive()) {
            return 0;
        }
        return round($htPrice * ($taxRate / 100), 2);
    }

    public static function calculateTTC(float $htPrice, float $taxRate): float
    {
        if (!self::isTaxActive()) {
            return round($htPrice, 2);
        }
        $tax = self::calculateTax($htPrice, $taxRate);
        return round($htPrice + $tax, 2);
    }

    public static function calculateHT(float $ttcPrice, float $taxRate): float
    {
        if (!self::isTaxActive()) {
            return round($ttcPrice, 2);
        }
        return round($ttcPrice / (1 + ($taxRate / 100)), 2);
    }
}
