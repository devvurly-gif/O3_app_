<?php

namespace App\Console\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Exécute le travail d'une commande dans le contexte de chaque tenant.
 *
 * Le planificateur (service systemd o3-scheduler, `schedule:run`) tourne dans
 * le contexte CENTRAL : aucune base tenant n'y est connectée. Une commande qui
 * interroge directement ses modèles tombe alors sur la base centrale, qui
 * contient une copie du schéma tenant mais aucune donnée client — elle
 * « réussit » tous les jours sans jamais rien faire. C'est ce qui est arrivé à
 * la facturation périodique et aux alertes de stock et d'échéance.
 *
 * Sans tenant désigné, seuls les tenants en règle sont traités : un tenant
 * suspendu ou désactivé à la main n'a rien à recevoir, et un tenant en impayé
 * (lecture seule) ne doit pas voir de documents apparaître dans son dos — d'où
 * la distinction entre travaux qui écrivent et travaux qui ne font que lire.
 * Un tenant désigné explicitement (lancement manuel) est traité quel que soit
 * son statut.
 *
 * Un tenant en échec est journalisé puis passé : il ne doit pas priver les
 * suivants de leur tournée. La commande sort alors en échec, pour que le
 * problème se voie ailleurs que dans les logs.
 */
trait RunsForEachTenant
{
    /**
     * @param callable(Tenant): int $work   Exécuté dans le contexte du tenant ; renvoie un code de sortie.
     * @param bool                  $writes Le travail crée-t-il des données ? (sinon : lecture et notifications)
     * @param string|null           $only   ID d'un tenant unique, pour un lancement manuel.
     */
    protected function runForEachTenant(callable $work, bool $writes = true, ?string $only = null): int
    {
        $tenants = $this->tenantsToProcess($writes, $only);

        if ($tenants === null) {
            $this->error("Tenant '{$only}' introuvable.");

            return self::FAILURE;
        }

        if ($tenants->isEmpty()) {
            $this->warn('Aucun tenant à traiter.');

            return self::SUCCESS;
        }

        $originalTenant = tenant();
        $failed = [];

        foreach ($tenants as $tenant) {
            $this->line("<comment>[{$tenant->id}]</comment>");

            try {
                $exit = $tenant->run(fn () => $work($tenant));
            } catch (\Throwable $e) {
                // Tenant::run() ne revient pas au contexte d'origine quand le
                // travail lève une exception.
                $originalTenant ? tenancy()->initialize($originalTenant) : tenancy()->end();

                $exit = self::FAILURE;
                $this->error("  {$tenant->id} : échec — {$e->getMessage()}");
                Log::error("{$this->getName()} a échoué pour le tenant {$tenant->id} : {$e->getMessage()}", [
                    'tenant_id' => $tenant->id,
                    'exception' => $e,
                ]);
            }

            if ($exit !== self::SUCCESS) {
                $failed[] = $tenant->id;
            }
        }

        if ($failed !== []) {
            $this->newLine();
            $this->warn('Tenant(s) en échec : ' . implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>|null null si le tenant demandé n'existe pas
     */
    private function tenantsToProcess(bool $writes, ?string $only): ?Collection
    {
        if ($only !== null && $only !== '') {
            $tenant = Tenant::find($only);

            return $tenant ? collect([$tenant]) : null;
        }

        // Boucle plutôt que ->filter() : get() renvoie une TenantCollection (Collection<int|string, Model>)
        // dont PHPStan ne peut pas affiner le type en Collection<int, Tenant>. Même ordre, même filtre.
        $eligible = [];
        foreach (Tenant::query()->where('is_active', true)->orderBy('id')->get() as $tenant) {
            /** @var Tenant $tenant */
            if ($writes ? $tenant->canWrite() : $tenant->canRead()) {
                $eligible[] = $tenant;
            }
        }

        return collect($eligible);
    }
}
