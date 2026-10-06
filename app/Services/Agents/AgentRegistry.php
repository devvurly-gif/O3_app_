<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

    // ── Comptes utilisateurs des agents ──────────────────────────────

    /** Le rôle des comptes techniques d'agents : aucune permission, jamais de connexion à l'interface. */
    public const ROLE = 'agent_ia';

    public static function agentRole(): Role
    {
        // Aucune permission n'est jamais rattachée : les routes se protègent par permission, ce rôle est donc
        // refusé partout. Seule l'ability d'un jeton (émis à part, si un agent externe en a besoin) ouvre une route.
        return Role::firstOrCreate(['name' => self::ROLE], [
            'display_name' => 'Agent IA',
            'description'  => "Compte technique d'un agent IA : aucune permission, ne se connecte pas à l'interface",
            'is_system'    => true,
        ]);
    }

    /** L'adresse du compte d'un agent : propre à l'agent et au tenant, jamais partagée. */
    public static function emailFor(Agent $agent): string
    {
        $tenant = function_exists('tenant') && tenant() ? (string) tenant('id') : 'local';
        $slug = $agent->kind === 'custom' ? "perso-{$agent->id}" : $agent->domain;

        return "agent-ia.{$slug}@{$tenant}.o3app.local";
    }

    /** L'ability Sanctum réservée à l'agent (un jeton ne s'émet qu'à la demande, voir les commandes *:agent-token). */
    public static function abilityFor(Agent $agent): string
    {
        return $agent->kind === 'custom' ? "custom:agent-{$agent->id}" : "{$agent->domain}:agent";
    }

    /** Les agents qui n'ont pas encore de compte utilisateur. @return \Illuminate\Support\Collection<int, Agent> */
    public function agentsWithoutAccount(): \Illuminate\Support\Collection
    {
        return Agent::whereNull('user_id')->orderBy('id')->get();
    }

    /**
     * Donne à un agent son propre compte utilisateur, s'il n'en a pas : un compte par agent, jamais partagé
     * (la révocation de l'un n'affecte jamais un autre). Le compte reflète l'état de l'agent (actif ou non),
     * porte le rôle sans permission, un mot de passe aléatoire que personne ne connaît, et AUCUN jeton.
     * Idempotent : un agent déjà rattaché à un compte le garde tel quel.
     */
    public function ensureAccount(Agent $agent): ?User
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('roles')) {
            return null;
        }
        if ($agent->user_id && ($existing = User::withTrashed()->find($agent->user_id))) {
            return $existing;
        }

        $user = User::withTrashed()->firstOrNew(['email' => self::emailFor($agent)]);
        $isNew = !$user->exists;

        $user->name = 'Agent IA — ' . $agent->name;
        $user->is_active = (bool) $agent->is_active;
        if ($isNew) {
            $user->password = Str::password(48);   // jamais communiqué : ce compte ne se connecte pas à l'interface
            $user->role_id = self::agentRole()->id;
        }
        $user->save();
        if ($user->trashed()) {
            $user->restore();
        }

        $agent->forceFill(['user_id' => $user->id, 'ability' => $agent->ability ?: self::abilityFor($agent)])->save();

        return $user;
    }

    /** Un compte pour chaque agent qui n'en a pas. @return array<string, string> libellé de l'agent => « créé » */
    public function ensureAllAccounts(): array
    {
        $report = [];
        foreach ($this->agentsWithoutAccount() as $agent) {
            $report[$agent->name] = $this->ensureAccount($agent) ? 'créé' : 'impossible';
        }

        return $report;
    }

    /** Le compte suit l'état de l'agent : un agent désactivé ne laisse pas de compte actif. */
    public function syncAccountState(Agent $agent): void
    {
        if ($agent->user_id) {
            User::withTrashed()->whereKey($agent->user_id)->update(['is_active' => (bool) $agent->is_active]);
        }
    }
}
