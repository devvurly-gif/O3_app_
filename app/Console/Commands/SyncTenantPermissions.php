<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;

class SyncTenantPermissions extends Command
{
    protected $signature = 'tenants:sync-permissions
                            {--dry-run : Report what is missing without writing}
                            {--grant= : Comma-separated extra roles that receive the newly created permissions (admin always does)}';

    protected $description = "Create permission rows added to RolePermissionSeeder's catalogue in every tenant DB, without touching existing role grants";

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $extraRoles = array_filter(array_map('trim', explode(',', (string) $this->option('grant'))));

        // Full catalogue as name => [module, action].
        $catalogue = [];
        foreach (RolePermissionSeeder::modules() as $module => $actions) {
            foreach ($actions as $action) {
                $catalogue["{$module}.{$action}"] = [$module, $action];
            }
        }

        $created = 0;
        $failed = 0;

        Tenant::all()->each(function (Tenant $tenant) use ($catalogue, $dryRun, $extraRoles, &$created, &$failed) {
            try {
                $tenant->run(function () use ($tenant, $catalogue, $dryRun, $extraRoles, &$created) {
                    $existing = Permission::pluck('id', 'name');
                    $missing = array_diff_key($catalogue, $existing->toArray());

                    if (! $missing) {
                        return;
                    }

                    $this->warn("  {$tenant->id}: " . count($missing) . ' missing — ' . implode(', ', array_keys($missing)));

                    if ($dryRun) {
                        return;
                    }

                    $newIds = [];
                    foreach ($missing as $name => [$module, $action]) {
                        $newIds[] = Permission::create([
                            'name'         => $name,
                            'module'       => $module,
                            'action'       => $action,
                            'display_name' => RolePermissionSeeder::displayNameFor($module, $action),
                        ])->id;
                        $created++;
                    }

                    // attach(), never sync() — an admin may have tailored these
                    // roles in the Roles screen and a sync would discard that.
                    foreach (array_merge(['admin'], $extraRoles) as $roleName) {
                        $role = Role::where('name', $roleName)->first();
                        if (! $role) {
                            $this->warn("    role '{$roleName}' not found on {$tenant->id} — skipped");
                            continue;
                        }
                        $role->permissions()->syncWithoutDetaching($newIds);
                        $this->info("    granted to '{$roleName}'");
                    }
                });
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ! skipped tenant '{$tenant->id}': {$e->getMessage()}");
            }
        });

        $this->newLine();
        if ($created === 0) {
            $this->info($dryRun ? 'Dry run complete — nothing written.' : 'All tenants already up to date.');
        } else {
            $this->info("{$created} permission row(s) created.");
        }
        if ($failed) {
            $this->warn("{$failed} tenant(s) could not be processed.");
        }

        return self::SUCCESS;
    }
}
