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
    //
    // SECURITY: whitelist both folder prefix and extension. Only
    // subfolders that the app actually writes to are reachable
    // (`products/`, `slides/`, `logos/`, `promotions/`), and only
    // image MIME types. This closes the open `.*` pattern which
    // previously let any anon visitor download anything ever
    // written to the public disk.
    //
    // Defense-in-depth: also reject `..` and leading `/` inside
    // the closure in case the whitelist regex is ever relaxed.
    Route::get('/storage/{path}', function (string $path) {
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }
        return response()->file($disk->path($path));
    })
        ->where('path', '(products|slides|logos|promotions)/[A-Za-z0-9._\-]+\.(jpe?g|png|webp|svg|gif|avif)')
        ->name('tenant.storage');

    // SPA catch-all (exclude api and storage paths)
    Route::get('/{any}', function () {
        return view('welcome');
    })->where('any', '^(?!api|storage|tenancy).*$');
});
