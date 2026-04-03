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
| API routes MUST be registered BEFORE the web catch-all,
| otherwise /{any} intercepts /api/* requests.
|
*/

// ── 1. Tenant API (must come first) ─────────────────────────────────────
Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api')->group(base_path('routes/api.php'));

// ── 2. Tenant Web ───────────────────────────────────────────────────────
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

    // SPA catch-all (exclude api and storage paths)
    Route::get('/{any}', function () {
        return view('welcome');
    })->where('any', '^(?!api|storage).*$');
});
