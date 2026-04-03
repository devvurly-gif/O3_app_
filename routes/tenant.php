<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| All tenant-scoped routes. Each tenant accesses the app via their own
| subdomain (e.g. client1.o3app.com). The middleware initializes tenancy
| automatically, switching to the tenant's database.
|
*/

// ── Tenant Web ───────────────────────────────────────────────────────────
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Serve tenant storage files (images, etc.)
    Route::get('/storage/{path}', function (string $path) {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }
        return response()->file($disk->path($path));
    })->where('path', '.*')->name('tenant.storage');

    // SPA catch-all
    Route::get('/{any}', function () {
        return view('welcome');
    })->where('any', '.*');
});

// ── Tenant API ───────────────────────────────────────────────────────────
Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api')->group(base_path('routes/api.php'));
