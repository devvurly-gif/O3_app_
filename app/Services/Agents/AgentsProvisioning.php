<?php

namespace App\Services\Agents;

use App\Enums\TenantStatus;
use App\Models\Agent;
use App\Models\Tenant;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Support\Facades\Schema;

/**
 * Ouverture des agents IA à un client, depuis la gestion des tenants (super-administrateur).
 *
 * Règle commerciale : les agents IA ne sont proposés qu'aux formules Pro et Business (config « plans.agents_plans »)
 * dont l'abonnement est PAYÉ (statut « actif ») : ni pendant l'essai gratuit, ni en retard de paiement, ni suspendu.
 * L'option est éteinte par défaut ; l'allumer depuis la gestion des tenants INSTALLE le socle des agents dans la base du
 * client (les sept agents, leurs règles de routage et leurs seuils de départ — AgentFoundationSeeder, sans doublon).
 * L'éteindre ferme l'accès mais ne supprime rien : les agents, routines et historique reviennent tels quels au rallumage.
 */
class AgentsProvisioning
{
    /** @return array<int, string> */
    public static function eligiblePlans(): array
    {
        return array_values((array) config('plans.agents_plans', ['pro', 'business']));
    }

    /**
     * Pourquoi les agents ne sont pas disponibles pour ce tenant (null s'ils le sont).
     *
     * @param string|null $plan formule envisagée (celle du tenant si absente)
     */
    public function unavailableReason(Tenant $tenant, ?string $plan = null): ?string
    {
        $plan = strtolower((string) ($plan ?? $tenant->plan));
        if (!in_array($plan, self::eligiblePlans(), true)) {
            return 'Les agents IA sont réservés aux formules ' . implode(' et ', array_map('ucfirst', self::eligiblePlans())) . " (formule actuelle : {$plan}).";
        }
        if ($tenant->currentStatus() !== TenantStatus::Active) {
            return "Les agents IA s'ouvrent après le paiement de la formule (statut actuel : " . $tenant->currentStatus()->label() . ').';
        }

        return null;
    }

    /**
     * Allume l'option et installe le socle des agents. Idempotent : relancer ne crée aucun doublon.
     *
     * @return array{installed: int, total: int} agents créés cette fois, et agents du socle présents ensuite
     * @throws \DomainException si la formule ou le paiement ne le permettent pas
     */
    public function enable(Tenant $tenant, ?string $plan = null): array
    {
        $reason = $this->unavailableReason($tenant, $plan);
        if ($reason !== null) {
            throw new \DomainException($reason);
        }

        $counts = $tenant->run(function () {
            if (!Schema::hasTable('agents')) {
                throw new \DomainException("La base de ce client n'a pas les tables des agents : lancez les migrations des tenants avant d'allumer l'option.");
            }
            $before = Agent::count();
            (new AgentFoundationSeeder())->run();

            return ['installed' => Agent::count() - $before, 'total' => Agent::count()];
        });

        $tenant->agents_enabled = true;
        $tenant->save();

        return $counts;
    }

    /** Éteint l'option ; rien n'est supprimé. */
    public function disable(Tenant $tenant): void
    {
        $tenant->agents_enabled = false;
        $tenant->save();
    }
}
