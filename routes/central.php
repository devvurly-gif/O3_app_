<?php

use App\Http\Controllers\Api\Central\PlanCatalogController;
use App\Http\Controllers\Api\Central\PublicRegistrationController;
use App\Http\Controllers\Api\Central\TenantController;
use App\Http\Controllers\Api\Central\TenantInvoiceController;
use App\Http\Controllers\Api\Central\TenantSubscriptionController;
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
    Route::get('tenants/{tenant}/url-status',     [TenantController::class, 'urlStatus']);
    Route::post('tenants/scrape-products',          [TenantController::class, 'scrapeProducts']);
    Route::post('tenants/{tenant}/import-products', [TenantController::class, 'importProducts']);

    // Catalogue des formules (valeurs du code, modifiables ci-dessous : config/plans.php + plan_overrides).
    Route::get('plans', [TenantSubscriptionController::class, 'plans']);

    // Personnalisation des formules, prix et options depuis la gestion des tenants.
    Route::get('plan-catalog',                    [PlanCatalogController::class, 'index']);
    Route::post('plan-catalog/plans',              [PlanCatalogController::class, 'createPlan']);
    Route::put('plan-catalog/plans/{key}',        [PlanCatalogController::class, 'updatePlan']);
    Route::delete('plan-catalog/plans/{key}',     [PlanCatalogController::class, 'resetPlan']);
    Route::put('plan-catalog/addons/{key}',       [PlanCatalogController::class, 'updateAddon']);

    // Abonnement : consultation, encaissement, changement de formule.
    Route::get('tenants/{tenant}/subscription',          [TenantSubscriptionController::class, 'show']);
    Route::put('tenants/{tenant}/subscription',          [TenantSubscriptionController::class, 'updatePlan']);
    Route::post('tenants/{tenant}/subscription/payment', [TenantSubscriptionController::class, 'recordPayment']);

    // Factures d'abonnement : émission manuelle, téléchargement, renvoi,
    // annulation. L'émission automatique passe par `subscriptions:invoice`.
    Route::get('tenants/{tenant}/invoices',  [TenantInvoiceController::class, 'index']);
    Route::post('tenants/{tenant}/invoices', [TenantInvoiceController::class, 'store']);
    Route::get('invoices/{invoice}/pdf',     [TenantInvoiceController::class, 'pdf']);
    Route::post('invoices/{invoice}/send',   [TenantInvoiceController::class, 'send']);
    Route::post('invoices/{invoice}/cancel', [TenantInvoiceController::class, 'cancel']);

    // Service contract: download template + send by email for e-signature
    Route::get('tenants/{tenant}/contract',         [TenantController::class, 'downloadContract']);
    Route::post('tenants/{tenant}/contract/send',   [TenantController::class, 'sendContract']);
});
