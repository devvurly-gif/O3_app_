<?php

namespace App\Providers;

use App\Services\DynamicMailService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class DynamicConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only apply if settings table exists (avoids errors during migration)
        try {
            if (Schema::hasTable('settings')) {
                DynamicMailService::applySettings();
            }
        } catch (\Throwable $e) {
            // Silently fail during migrations or when DB is not ready
        }
    }
}
