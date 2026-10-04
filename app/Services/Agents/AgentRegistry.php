<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache les comptes techniques Sanctum des agents IA à la table `agents`.
 *
 * Le compte (User) et son jeton restent la seule source d'identité et de droits :
 * `agents` ne stocke que le lien (user_id) et l'ability principale, pour que le
 * routeur et le journal d'audit sachent quel agent est quel compte.
 */
class AgentRegistry
{
    /** Comptes techniques existants, par domaine (voir les commandes *:agent-token). */
    public const ACCOUNTS = [
        'achats' => ['email' => 'agent-ia.achats@jadema.o3app.local', 'name' => 'Achats & catalogue', 'ability' => 'achats:import'],
        'ventes' => ['email' => 'agent-ia.ventes@jadema.o3app.local', 'name' => 'Ventes & CRM', 'ability' => 'ventes:whatsapp-import'],
    ];

    /**
     * Crée ou met à jour l'agent du domaine. Ne modifie ni son nom ni son
     * statut actif s'ils existent déjà (réglages manuels conservés).
     */
    public function register(string $domain, User $user, string $ability, ?string $name = null): ?Agent
    {
        // Tenant pas encore migré vers le socle : les commandes *:agent-token
        // existantes doivent continuer à fonctionner.
        if (!Schema::hasTable('agents')) {
            return null;
        }

        $agent = Agent::firstOrNew(['domain' => $domain]);

        if (!$agent->exists) {
            $agent->name = $name ?? ucfirst($domain);
            $agent->is_active = true;
        }
        $agent->user_id = $user->id;
        $agent->ability = $ability;
        $agent->save();

        return $agent;
    }

    /**
     * Rattache les comptes déjà créés, sans émettre de jeton.
     *
     * @return array<string, string> domaine => « lié » | « compte introuvable »
     */
    public function linkExistingAccounts(): array
    {
        $report = [];

        foreach (self::ACCOUNTS as $domain => $account) {
            $user = User::where('email', $account['email'])->first();
            if (!$user) {
                $report[$domain] = 'compte introuvable';
                continue;
            }
            $this->register($domain, $user, $account['ability'], $account['name']);
            $report[$domain] = 'lié';
        }

        return $report;
    }
}
