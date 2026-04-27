<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the central `modules` table. Feature gating now reads tenant flags
 * (pos_enabled, ecom_enabled, …) directly from the central `tenants` table —
 * see App\Http\Middleware\CheckTenantFeature and App\Services\AuthService.
 *
 * Tenant DBs already had their `modules` table dropped in tenant migration
 * 2026_04_19_drop_modules_table.php (now removed alongside this cleanup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('modules');
    }

    public function down(): void
    {
        // No rollback — modules concept is fully removed.
    }
};
