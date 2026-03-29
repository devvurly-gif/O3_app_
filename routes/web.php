<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Central Domain Only)
|--------------------------------------------------------------------------
|
| These routes are only accessible on central domains (admin panel).
| Tenant domains are handled by routes/tenant.php.
|
*/

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {

        // Central app API (auth, CRUD, etc.)
        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api.php'));

        // Central tenant management API
        Route::middleware('api')->group(
            base_path('routes/central.php')
        );

        // SPA catch-all for central admin panel
        Route::get('/{any}', function () {
            return view('welcome');
        })->where('any', '.*');
    });
}
