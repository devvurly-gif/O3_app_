<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * RefreshDatabase, but migrating the schema tenants actually run.
 *
 * `database/migrations/` holds the central tables (tenants, domains) plus a
 * copy of the tenant schema that stopped being maintained: the newest tenant
 * migrations — variants, push subscriptions, dashboard widgets — only ever
 * landed in `database/migrations/tenant/`. Feature tests exercise tenant
 * models against that stale copy, and `Product::$total_stock` queries
 * `product_variants` on every serialisation, so anything touching a product
 * used to 500 in the test suite.
 *
 * Passing both paths fixes it: Migrator::getMigrationFiles() keys files by
 * migration name, so for the ~62 duplicated names the later path wins and the
 * central-only migrations still come along.
 *
 * Use this in place of RefreshDatabase. Note that migrateFreshUsing() has to
 * live in a trait rather than in Tests\TestCase — a trait method inserted in
 * the test class takes precedence over an inherited one, so RefreshDatabase's
 * own default would win over a parent-class override.
 */
trait RefreshTenantDatabase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        $seeder = $this->seeder();

        return array_merge(
            [
                '--path' => [
                    database_path('migrations'),
                    database_path('migrations/tenant'),
                ],
                '--realpath'   => true,
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
            ],
            $seeder ? ['--seeder' => $seeder] : ['--seed' => $this->shouldSeed()],
        );
    }
}
