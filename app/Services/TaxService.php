<?php

namespace App\Services;

use App\Models\Setting;

class TaxService
{
    public static function isTaxActive(): bool
    {
        return Setting::get('invoice', 'tax_enabled', 'true') === 'true';
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
