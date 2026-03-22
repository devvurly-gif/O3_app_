<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Seed all permissions ─────────────────────────────────────
        $modules = [
            'users'          => ['view', 'create', 'update', 'delete'],
            'roles'          => ['view', 'create', 'update', 'delete'],
            'products'       => ['view', 'create', 'update', 'delete'],
            'categories'     => ['view', 'create', 'update', 'delete'],
            'brands'         => ['view', 'create', 'update', 'delete'],
            'third_partners' => ['view', 'create', 'update', 'delete'],
            'documents'      => ['view', 'create', 'update', 'delete', 'confirm', 'cancel'],
            'payments'       => ['view', 'create', 'delete'],
            'stock'          => ['view', 'manage', 'transfer', 'adjust'],
            'warehouses'     => ['view', 'create', 'update', 'delete'],
            'settings'       => ['view', 'manage'],
            'pos'            => ['access', 'manage_terminals', 'open_session', 'close_session', 'void_ticket'],
        ];

        $displayNames = [
            'view' => 'Voir', 'create' => 'Créer', 'update' => 'Modifier',
            'delete' => 'Supprimer', 'confirm' => 'Confirmer', 'cancel' => 'Annuler',
            'manage' => 'Gérer', 'transfer' => 'Transférer', 'adjust' => 'Ajuster',
            'access' => 'Accéder', 'manage_terminals' => 'Gérer terminaux',
            'open_session' => 'Ouvrir session', 'close_session' => 'Fermer session',
            'void_ticket' => 'Annuler ticket',
        ];

        $moduleLabels = [
            'users' => 'Utilisateurs', 'roles' => 'Rôles', 'products' => 'Produits',
            'categories' => 'Catégories', 'brands' => 'Marques', 'third_partners' => 'Tiers',
            'documents' => 'Documents', 'payments' => 'Paiements', 'stock' => 'Stock',
            'warehouses' => 'Entrepôts', 'settings' => 'Paramètres', 'pos' => 'Point de Vente',
        ];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(
                    ['name' => "{$module}.{$action}"],
                    [
                        'module'       => $module,
                        'action'       => $action,
                        'display_name' => ($moduleLabels[$module] ?? ucfirst($module)) . ' — ' . ($displayNames[$action] ?? ucfirst($action)),
                    ]
                );
            }
        }

        // ── Seed default role-permission mappings ────────────────────
        $allPermissions = Permission::all()->pluck('id', 'name');

        // Admin: ALL permissions
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrateur', 'description' => 'Accès complet', 'is_system' => true]
        );
        $admin->permissions()->sync($allPermissions->values()->toArray());

        // Manager: everything except users.*, roles.*, settings.manage
        $manager = Role::firstOrCreate(
            ['name' => 'manager'],
            ['display_name' => 'Gestionnaire', 'description' => 'Gestion catalogue, documents, stock', 'is_system' => true]
        );
        $managerPerms = $allPermissions->filter(function ($id, $name) {
            return !str_starts_with($name, 'users.')
                && !str_starts_with($name, 'roles.')
                && $name !== 'settings.manage';
        });
        $manager->permissions()->sync($managerPerms->values()->toArray());

        // Cashier: *.view + documents.* + payments.* + third_partners.create/update
        $cashier = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Caissier', 'description' => 'Gestion documents et paiements', 'is_system' => true]
        );
        $cashierPerms = $allPermissions->filter(function ($id, $name) {
            if (str_ends_with($name, '.view')) return true;
            if (str_starts_with($name, 'documents.')) return true;
            if (str_starts_with($name, 'payments.')) return true;
            if (in_array($name, ['third_partners.create', 'third_partners.update'])) return true;
            if (in_array($name, ['pos.access', 'pos.open_session', 'pos.close_session'])) return true;
            return false;
        });
        $cashier->permissions()->sync($cashierPerms->values()->toArray());

        // Warehouse: *.view + stock.* + warehouses.*
        $warehouse = Role::firstOrCreate(
            ['name' => 'warehouse'],
            ['display_name' => 'Magasinier', 'description' => 'Gestion stock et entrepôts', 'is_system' => true]
        );
        $warehousePerms = $allPermissions->filter(function ($id, $name) {
            if (str_ends_with($name, '.view')) return true;
            if (str_starts_with($name, 'stock.')) return true;
            if (str_starts_with($name, 'warehouses.')) return true;
            return false;
        });
        $warehouse->permissions()->sync($warehousePerms->values()->toArray());
    }
}
