<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\RoleCloneService;
use Illuminate\Console\Command;

/**
 * Applique RoleCloneService à un tenant ou à tous.
 *
 * La commande ne fait que la boucle et l'affichage : la composition du rôle
 * vit dans le service, sur la connexion courante. C'est ce qui la rend
 * vérifiable — la table `tenants` est vide en test, et une logique enfermée
 * dans cette boucle ne s'y exécuterait jamais.
 */
class CloneTenantRole extends Command
{
    protected $signature = 'tenants:clone-role
                            {source : Nom du rôle à recopier, ex. manager}
                            {name : Nom technique du nouveau rôle, ex. manager_remises}
                            {--display= : Libellé affiché, ex. "Manager · remises"}
                            {--add= : Permissions à ajouter, séparées par des virgules}
                            {--remove= : Permissions à retirer, séparées par des virgules}
                            {--tenant= : Se limiter à un tenant}
                            {--dry-run : Montrer ce qui serait fait, sans écrire}';

    protected $description = "Crée un rôle par recopie des permissions d'un autre, dans un ou tous les tenants";

    public function handle(RoleCloneService $cloner): int
    {
        $source  = (string) $this->argument('source');
        $name    = (string) $this->argument('name');
        $display = (string) $this->option('display');
        $add     = $this->list((string) $this->option('add'));
        $remove  = $this->list((string) $this->option('remove'));
        $dryRun  = (bool) $this->option('dry-run');
        $only    = (string) $this->option('tenant');

        // Stancl type `Tenant::all()` en Collection<Model> : sans cette
        // annotation, chaque `$tenant->id` et chaque `$tenant->run()` en aval
        // devient invérifiable. La déclarer ici vaut mieux qu'un motif
        // d'exception global qui masquerait aussi les vraies erreurs.
        /** @var \Illuminate\Database\Eloquent\Collection<int, Tenant> $tenants */
        $tenants = Tenant::all();

        if ($only !== '') {
            $tenants = $tenants->filter(fn (Tenant $t) => $t->id === $only);
        }

        if ($tenants->isEmpty()) {
            $this->error($only !== '' ? "Tenant « {$only} » introuvable." : 'Aucun tenant.');

            return self::FAILURE;
        }

        $done = 0;

        foreach ($tenants as $tenant) {
            $tenant->run(function () use (
                $tenant, $cloner, $source, $name, $display, $add, $remove, $dryRun, &$done
            ) {
                $r = $cloner->clone($source, $name, $display ?: null, $add, $remove, $dryRun);

                if ($r['status'] === 'source_absente') {
                    $this->warn("  {$tenant->id} : rôle source « {$source} » absent — ignoré");

                    return;
                }

                if ($r['unknown']) {
                    $this->warn("  {$tenant->id} : permissions inconnues — " . implode(', ', $r['unknown']));
                }

                $this->line(sprintf(
                    '  %-16s %-9s %d permission(s)%s',
                    $tenant->id,
                    $r['status'],
                    $r['permissions'],
                    $dryRun ? '  [simulation]' : '',
                ));

                $done++;
            });
        }

        $this->newLine();
        $this->info("{$done} tenant(s) traité(s).");

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function list(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
