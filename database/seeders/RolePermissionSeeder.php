<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Catalogue of every permission the app knows about, as module => actions.
     *
     * Exposed statically so `tenants:sync-permissions` can create missing rows
     * on existing tenants without re-running run(), whose role sync() calls
     * would wipe any custom grant an admin made in the Roles screen.
     *
     * @return array<string, string[]>
     */
    public static function modules(): array
    {
        return [
            'users'          => ['view', 'create', 'update', 'delete'],
            'roles'          => ['view', 'create', 'update', 'delete'],
            // view_cost gates the cost-bearing fields (prix d'achat, coût) in
            // the API payload and the product list columns — not the page itself.
            'products'       => ['view', 'create', 'update', 'delete', 'view_cost'],
            'categories'     => ['view', 'create', 'update', 'delete'],
            'brands'         => ['view', 'create', 'update', 'delete'],
            'third_partners' => ['view', 'create', 'update', 'delete'],
            'documents'      => ['view', 'create', 'update', 'delete', 'confirm', 'cancel'],
            'payments'       => ['view', 'create', 'delete'],
            'stock'          => ['view', 'manage', 'transfer', 'adjust'],
            'warehouses'     => ['view', 'create', 'update', 'delete'],
            // treasury.manage couvre le paramétrage (comptes, postes, récurrences),
            // là où create/update ne couvrent que la saisie d'écritures.
            'treasury'       => ['view', 'create', 'update', 'delete', 'manage'],
            'settings'       => ['view', 'manage'],
            'pos'            => ['access', 'manage_terminals', 'open_session', 'close_session', 'void_ticket', 'override_price'],
        ];
    }

    /** @return array<string, string> action => label */
    public static function actionLabels(): array
    {
        return [
            'view' => 'Voir', 'create' => 'Créer', 'update' => 'Modifier',
            'delete' => 'Supprimer', 'confirm' => 'Confirmer', 'cancel' => 'Annuler',
            'manage' => 'Gérer', 'transfer' => 'Transférer', 'adjust' => 'Ajuster',
            'access' => 'Accéder', 'manage_terminals' => 'Gérer terminaux',
            'open_session' => 'Ouvrir session', 'close_session' => 'Fermer session',
            'void_ticket' => 'Annuler ticket',
            'view_cost' => "Voir prix d'achat / coût",
        ];
    }

    /** @return array<string, string> module => label */
    public static function moduleLabels(): array
    {
        return [
            'users' => 'Utilisateurs', 'roles' => 'Rôles', 'products' => 'Produits',
            'categories' => 'Catégories', 'brands' => 'Marques', 'third_partners' => 'Tiers',
            'documents' => 'Documents', 'payments' => 'Paiements', 'stock' => 'Stock',
            'warehouses' => 'Entrepôts', 'settings' => 'Paramètres', 'pos' => 'Point de Vente',
            'treasury' => 'Trésorerie',
        ];
    }

    /**
     * Human label for a permission name, e.g. "Produits — Voir prix d'achat / cout".
     */
    public static function displayNameFor(string $module, string $action): string
    {
        return (static::moduleLabels()[$module] ?? ucfirst($module))
            . ' — ' . (static::actionLabels()[$action] ?? ucfirst($action));
    }

    /**
     * Every permission name in the catalogue, e.g. "warehouses.create".
     *
     * @return string[]
     */
    public static function permissionNames(): array
    {
        $names = [];

        foreach (static::modules() as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    /** @return array<string, array{display_name: string, description: string}> */
    public static function systemRoles(): array
    {
        return [
            'admin'     => ['display_name' => 'Administrateur', 'description' => 'Accès complet'],
            'manager'   => ['display_name' => 'Gestionnaire',   'description' => 'Gestion catalogue, documents, stock'],
            'cashier'   => ['display_name' => 'Caissier',       'description' => 'Gestion documents et paiements'],
            'warehouse' => ['display_name' => 'Magasinier',     'description' => 'Gestion stock et entrepôts'],
        ];
    }

    /**
     * Default grants of a system role, as permission names.
     *
     * Routes are guarded by permission, so this mapping is what actually
     * decides who may act — UserFactory reads it too, otherwise a test role
     * would carry no grant at all and every permission guard would deny it.
     *
     * @return string[]
     */
    public static function permissionNamesFor(string $role): array
    {
        $all = static::permissionNames();

        $keep = match ($role) {
            'admin' => static fn (string $name): bool => true,

            // Manager: everything except users.*, roles.*, settings.manage
            'manager' => static fn (string $name): bool => !str_starts_with($name, 'users.')
                && !str_starts_with($name, 'roles.')
                && $name !== 'settings.manage',

            // Cashier: *.view + documents.* + payments.* + third_partners.create/update
            'cashier' => static function (string $name): bool {
                if (str_ends_with($name, '.view')) return true;
                if (str_starts_with($name, 'documents.')) return true;
                if (str_starts_with($name, 'payments.')) return true;
                if (in_array($name, ['third_partners.create', 'third_partners.update'])) return true;
                // Le caissier saisit les dépenses courantes de la journée, mais ne
                // touche pas au plan de comptes (treasury.manage/delete).
                if (in_array($name, ['treasury.create', 'treasury.update'])) return true;
                if (in_array($name, ['pos.access', 'pos.open_session', 'pos.close_session'])) return true;
                return false;
            },

            // Warehouse: *.view + stock.* + warehouses.*
            'warehouse' => static function (string $name): bool {
                if (str_ends_with($name, '.view')) return true;
                if (str_starts_with($name, 'stock.')) return true;
                if (str_starts_with($name, 'warehouses.')) return true;
                // Stock/purchase document forms prefill unit_price from
                // p_purchasePrice, so this role needs the cost fields.
                if ($name === 'products.view_cost') return true;
                return false;
            },

            default => static fn (string $name): bool => false,
        };

        return array_values(array_filter($all, $keep));
    }

    public function run(): void
    {
        // ── Seed all permissions ─────────────────────────────────────
        foreach (static::modules() as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(
                    ['name' => "{$module}.{$action}"],
                    [
                        'module'       => $module,
                        'action'       => $action,
                        'display_name' => static::displayNameFor($module, $action),
                    ]
                );
            }
        }

        // ── Seed default role-permission mappings ────────────────────
        $permissionIds = Permission::all()->pluck('id', 'name');

        foreach (static::systemRoles() as $name => $attributes) {
            $role = Role::firstOrCreate(['name' => $name], $attributes + ['is_system' => true]);

            $role->permissions()->sync(
                $permissionIds->only(static::permissionNamesFor($name))->values()->all()
            );
        }
    }
}
