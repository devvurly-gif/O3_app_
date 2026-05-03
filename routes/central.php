<?php

use App\Http\Controllers\Api\Central\PublicRegistrationController;
use App\Http\Controllers\Api\Central\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API Routes
|--------------------------------------------------------------------------
|
| These routes are for managing tenants from the central admin panel.
| Accessible only from central domains (admin.o3app.com / localhost).
|
*/

// ── Public registration (no auth) ─────────────────────────────────────
// Tight rate limit on the create endpoint: provisioning a tenant
// allocates a real database, so each accepted POST is expensive. The
// availability check + verify endpoint can tolerate higher throughput.
Route::prefix('api/central/register')->middleware('api')->group(function () {
    Route::get('check-subdomain', [PublicRegistrationController::class, 'checkSubdomain'])
        ->middleware('throttle:30,1');
    Route::post('/', [PublicRegistrationController::class, 'register'])
        ->middleware('throttle:3,60'); // 3 attempts per IP per hour
    Route::post('verify', [PublicRegistrationController::class, 'verify'])
        ->middleware('throttle:20,1');
});

Route::prefix('api/central')->middleware(['api', 'auth:sanctum', 'role:admin'])->group(function () {
    Route::get('tenants',              [TenantController::class, 'index']);
    Route::get('tenants/{tenant}',     [TenantController::class, 'show']);
    Route::post('tenants',             [TenantController::class, 'store']);
    Route::put('tenants/{tenant}',     [TenantController::class, 'update']);
    Route::delete('tenants/{tenant}',  [TenantController::class, 'destroy']);
    Route::post('tenants/{tenant}/reset-password', [TenantController::class, 'resetPassword']);
    Route::post('tenants/{tenant}/reset-database', [TenantController::class, 'resetDatabase']);
    Route::post('tenants/{tenant}/purge-files',    [TenantController::class, 'purgeFiles']);
    Route::post('tenants/scrape-products',          [TenantController::class, 'scrapeProducts']);
    Route::post('tenants/{tenant}/import-products', [TenantController::class, 'importProducts']);

    // Service contract: download template + send by email for e-signature
    Route::get('tenants/{tenant}/contract',         [TenantController::class, 'downloadContract']);
    Route::post('tenants/{tenant}/contract/send',   [TenantController::class, 'sendContract']);
});
