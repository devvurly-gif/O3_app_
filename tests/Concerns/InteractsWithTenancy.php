<?php

namespace Tests\Concerns;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;

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

    /**
     * Tenants réels, enregistrés dans la table centrale, dont `$tenant->run()`
     * initialise vraiment la tenancy — mais sur l'unique base de test.
     *
     * Les commandes planifiées tournent depuis le contexte central et doivent
     * entrer elles-mêmes dans chaque tenant. Pour l'éprouver, il faut que
     * `run()` fonctionne sans base `tenant<id>` : le DatabaseTenancyBootstrapper,
     * qui exige en test que cette base existe, est donc retiré. Les autres
     * bootstrappers (cache, fichiers, file d'attente) restent en place.
     *
     * @param array<string, array<string, mixed>> $tenants id => attributs (écrasent les valeurs par défaut)
     * @return array<string, Tenant>
     */
    protected function tenantsOnTheTestDatabase(array $tenants = ['test-tenant' => []]): array
    {
        config(['tenancy.bootstrappers' => array_values(array_diff(
            config('tenancy.bootstrappers'),
            [DatabaseTenancyBootstrapper::class],
        ))]);

        $created = [];

        foreach ($tenants as $id => $attributes) {
            $tenant = new Tenant();
            $tenant->id = $id;

            $defaults = [
                'name'                 => "Tenant {$id}",
                'email'                => "{$id}@test.ma",
                'plan'                 => 'pro',
                'status'               => TenantStatus::Active,
                'is_active'            => true,
                'subscription_ends_at' => now()->addYear(),
            ];

            foreach (array_merge($defaults, $attributes) as $attribute => $value) {
                $tenant->{$attribute} = $value;
            }

            // Sans événements : Stancl créerait et migrerait une vraie base.
            Tenant::withoutEvents(fn () => $tenant->save());

            $created[$id] = $tenant;
        }

        return $created;
    }

    /**
     * Donne à chaque passage dans un tenant sa propre « base ».
     *
     * Tous les tenants partagent la base de test : sans cela, le deuxième
     * verrait les données du premier. L'entrée dans un tenant ouvre donc une
     * transaction (un savepoint dans celle de RefreshDatabase) où `$seed` pose
     * les données propres au tenant ; la sortie laisse `$inspect` relever ce
     * que la commande y a fait, puis annule le tout.
     *
     * @param (callable(Tenant): void)|null $seed
     * @param (callable(Tenant): void)|null $inspect
     */
    protected function isolateEachTenantRun(?callable $seed = null, ?callable $inspect = null): void
    {
        Event::listen(TenancyInitialized::class, function (TenancyInitialized $event) use ($seed) {
            DB::beginTransaction();

            if ($seed) {
                $seed($event->tenancy->tenant);
            }
        });

        Event::listen(TenancyEnded::class, function (TenancyEnded $event) use ($inspect) {
            if ($inspect) {
                $inspect($event->tenancy->tenant);
            }

            DB::rollBack();
        });
    }
}
