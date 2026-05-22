<?php

namespace App\Services;

use App\Models\Setting;

/**
 * PackageService - Manages business packages and their features.
 * 
 * Features can be enabled/disabled per package:
 * - Basic: Standard invoicing only
 * - Professional: + Price lists, Reports
 * - Business: + OCR Import, Advanced Analytics
 */
class PackageService
{
    const PACKAGE_BASIC = 'basic';
    const PACKAGE_PROFESSIONAL = 'professional';
    const PACKAGE_BUSINESS = 'business';

    const FEATURE_OCR_IMPORT = 'ocr_import';
    const FEATURE_PRICE_LISTS = 'price_lists';
    const FEATURE_REPORTS = 'reports';
    const FEATURE_ANALYTICS = 'analytics';
    const FEATURE_MULTI_WAREHOUSE = 'multi_warehouse';
    const FEATURE_POS = 'pos';

    private static array  = [
        self::PACKAGE_BASIC => [
            self::FEATURE_POS => true,
        ],
        self::PACKAGE_PROFESSIONAL => [
            self::FEATURE_POS => true,
            self::FEATURE_PRICE_LISTS => true,
            self::FEATURE_REPORTS => true,
            self::FEATURE_MULTI_WAREHOUSE => true,
        ],
        self::PACKAGE_BUSINESS => [
            self::FEATURE_POS => true,
            self::FEATURE_PRICE_LISTS => true,
            self::FEATURE_REPORTS => true,
            self::FEATURE_MULTI_WAREHOUSE => true,
            self::FEATURE_OCR_IMPORT => true,
            self::FEATURE_ANALYTICS => true,
        ],
    ];

    /**
     * Get the current package type.
     */
    public static function getCurrentPackage(): string
    {
        return Setting::get('billing', 'package_type', self::PACKAGE_BASIC);
    }

    /**
     * Check if a feature is enabled for the current package.
     */
    public static function isFeatureEnabled(string ): bool
    {
         = self::getCurrentPackage();
        return self::[][] ?? false;
    }

    /**
     * Check if OCR import is enabled.
     */
    public static function isOcrImportEnabled(): bool
    {
        return self::isFeatureEnabled(self::FEATURE_OCR_IMPORT);
    }

    /**
     * Get all features for the current package.
     */
    public static function getCurrentPackageFeatures(): array
    {
         = self::getCurrentPackage();
        return self::[] ?? [];
    }

    /**
     * Get package information with pricing.
     */
    public static function getPackageInfo(string ): array
    {
        return [
            'id' => ,
            'name' => self::getPackageName(),
            'description' => self::getPackageDescription(),
            'features' => self::[] ?? [],
        ];
    }

    private static function getPackageName(string ): string
    {
        return match () {
            self::PACKAGE_BASIC => 'Basic',
            self::PACKAGE_PROFESSIONAL => 'Professional',
            self::PACKAGE_BUSINESS => 'Business',
            default => 'Unknown',
        };
    }

    private static function getPackageDescription(string ): string
    {
        return match () {
            self::PACKAGE_BASIC => 'Standard invoicing and POS',
            self::PACKAGE_PROFESSIONAL => 'With price lists and reports',
            self::PACKAGE_BUSINESS => 'Premium with OCR import and analytics',
            default => 'Unknown package',
        };
    }
}
