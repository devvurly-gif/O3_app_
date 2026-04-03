<?php

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

Route::prefix('api/central')->middleware(['api'])->group(function () {
    // TODO: add auth middleware for super-admin protection
    Route::get('tenants',              [TenantController::class, 'index']);
    Route::get('tenants/{tenant}',     [TenantController::class, 'show']);
    Route::post('tenants',             [TenantController::class, 'store']);
    Route::put('tenants/{tenant}',     [TenantController::class, 'update']);
    Route::delete('tenants/{tenant}',  [TenantController::class, 'destroy']);
    Route::post('tenants/{tenant}/reset-password', [TenantController::class, 'resetPassword']);
    Route::post('tenants/{tenant}/reset-database', [TenantController::class, 'resetDatabase']);
    Route::post('tenants/{tenant}/purge-files',    [TenantController::class, 'purgeFiles']);
});
