<?php

namespace App\Services;

use App\Models\Setting;

class PackageService
{
    const PACKAGE_BASIC        = 'basic';
    const PACKAGE_PROFESSIONAL = 'professional';
    const PACKAGE_BUSINESS     = 'business';

    const FEATURE_OCR_IMPORT      = 'ocr_import';
    const FEATURE_PRICE_LISTS     = 'price_lists';
    const FEATURE_REPORTS         = 'reports';
    const FEATURE_ANALYTICS       = 'analytics';
    const FEATURE_MULTI_WAREHOUSE = 'multi_warehouse';
    const FEATURE_POS             = 'pos';

    private static array $packages = [
        self::PACKAGE_BASIC => [
            self::FEATURE_POS => true,
        ],
        self::PACKAGE_PROFESSIONAL => [
            self::FEATURE_POS             => true,
            self::FEATURE_PRICE_LISTS     => true,
            self::FEATURE_REPORTS         => true,
            self::FEATURE_MULTI_WAREHOUSE => true,
        ],
        self::PACKAGE_BUSINESS => [
            self::FEATURE_POS             => true,
            self::FEATURE_PRICE_LISTS     => true,
            self::FEATURE_REPORTS         => true,
            self::FEATURE_MULTI_WAREHOUSE => true,
            self::FEATURE_OCR_IMPORT      => true,
            self::FEATURE_ANALYTICS       => true,
        ],
    ];

    public static function getCurrentPackage(): string
    {
        return Setting::get('billing', 'package_type', self::PACKAGE_BASIC);
    }

    public static function isFeatureEnabled(string $feature): bool
    {
        $package = self::getCurrentPackage();
        return self::$packages[$package][$feature] ?? false;
    }

    public static function isOcrImportEnabled(): bool
    {
        return self::isFeatureEnabled(self::FEATURE_OCR_IMPORT);
    }

    public static function getCurrentPackageFeatures(): array
    {
        $package = self::getCurrentPackage();
        return self::$packages[$package] ?? [];
    }

    public static function getPackageInfo(string $package): array
    {
        return [
            'id'          => $package,
            'name'        => self::getPackageName($package),
            'description' => self::getPackageDescription($package),
            'features'    => self::$packages[$package] ?? [],
        ];
    }

    private static function getPackageName(string $package): string
    {
        return match ($package) {
            self::PACKAGE_BASIC        => 'Basic',
            self::PACKAGE_PROFESSIONAL => 'Professional',
            self::PACKAGE_BUSINESS     => 'Business',
            default                    => 'Unknown',
        };
    }

    private static function getPackageDescription(string $package): string
    {
        return match ($package) {
            self::PACKAGE_BASIC        => 'Standard invoicing and POS',
            self::PACKAGE_PROFESSIONAL => 'With price lists and reports',
            self::PACKAGE_BUSINESS     => 'Premium with OCR import and analytics',
            default                    => 'Unknown package',
        };
    }
}
