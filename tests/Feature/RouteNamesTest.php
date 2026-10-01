<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `php artisan route:cache` (étape 4 de deploy.sh) refuse deux routes de même
 * nom. routes/api.php est enregistré plusieurs fois — une par domaine central
 * puis pour les tenants —, donc le moindre ->name() posé dedans fait échouer
 * le déploiement après que le code est déjà tiré sur le serveur.
 */
class RouteNamesTest extends TestCase
{
    public function test_no_route_name_is_declared_twice(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter();

        $this->assertSame(
            [],
            $names->duplicates()->values()->all(),
            'Noms de route en double : route:cache échouerait. Ne nommez pas les routes de routes/api.php.'
        );
    }
}
