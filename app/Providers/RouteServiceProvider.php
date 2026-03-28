<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // Central API routes (tenant management)
            $this->mapCentralRoutes();

            // Central API routes (used by both central & tenant via tenant.php)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Central web (SPA catch-all for central domains)
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Register central-only routes (tenant management panel).
     */
    protected function mapCentralRoutes(): void
    {
        foreach ($this->centralDomains() as $domain) {
            Route::domain($domain)
                ->group(base_path('routes/central.php'));
        }
    }

    protected function centralDomains(): array
    {
        return config('tenancy.central_domains', [
            'localhost',
            '127.0.0.1',
        ]);
    }
}
