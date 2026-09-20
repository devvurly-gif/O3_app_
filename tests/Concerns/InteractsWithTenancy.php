<?php

namespace Tests\Concerns;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;

/**
 * Lie un tenant en mémoire dans le conteneur, celui-là même que le helper
 * `tenant()` résout.
 *
 * La suite tourne sans tenancy initialisée : sans ce binding, `tenant()`
 * renvoie null et tout ce qui dépend du tenant — middleware `feature:`,
 * middleware `tenant.active`, capacités renvoyées par AuthService — se
 * comporte comme sur le domaine central.
 *
 * Extrait d'InteractsWithPos, où il avait été écrit pour la caisse : le statut
 * d'abonnement concerne désormais toutes les routes tenant, pas seulement le POS.
 */
trait InteractsWithTenancy
{
    /**
     * Tenant de test : abonnement en cours, caisse ouverte.
     *
     * Les attributs passés écrasent les valeurs par défaut — `status`,
     * `is_active` et `subscription_ends_at` compris, ce qui permet de tester
     * un compte échu ou suspendu.
     *
     * @param array<string, mixed> $attributes
     */
    protected function fakeTenant(array $attributes = []): Tenant
    {
        $tenant = new Tenant();
        $tenant->id   = 'test-tenant';
        $tenant->name = 'Tenant de test';

        $defaults = [
            'email'                => 'tenant@test.ma',
            // Abonnement : sans cela le middleware `tenant.active` refuserait
            // chaque requête, comme il le fait en production pour un compte échu.
            'status'               => TenantStatus::Trial,
            'is_active'            => true,
            'subscription_ends_at' => now()->addDays(14),
            'plan'                 => 'pro',
            // Capacités.
            'pos_enabled'      => true,
            'ecom_enabled'     => false,
            'variants_enabled' => false,
            'imei_enabled'     => false,
        ];

        foreach (array_merge($defaults, $attributes) as $attribute => $value) {
            $tenant->{$attribute} = $value;
        }

        // Le tenant est aussi écrit en base : tout ce qui le modifie — une
        // demande de formule, un encaissement — fait un save(), qui échouerait
        // sur un modèle purement en mémoire. `withoutEvents` évite au passage
        // la création d'une vraie base de données par Stancl.
        Tenant::withoutEvents(function () use ($tenant) {
            Tenant::query()->where('id', $tenant->id)->delete();
            $tenant->save();
        });

        $this->app->instance(TenantContract::class, $tenant);

        return $tenant;
    }
}
