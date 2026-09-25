<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Émet (ou réémet) le jeton Sanctum du compte de service « Agent IA », partagé
 * par les agents Claude de Jadema : saisie des achats (« factures
 * fournisseurs\importer\README.md », POST /api/achats/import) et facturation
 * client (« facturation clients\CLAUDE.md », POST /api/ventes/whatsapp-import).
 * Un seul compte, un seul jeton, deux abilities Sanctum distinctes — chaque
 * route vérifie la sienne (PurchaseImportRequest::authorize() ->
 * tokenCan('achats:import'), WhatsAppOrderImportRequest::authorize() ->
 * tokenCan('ventes:whatsapp-import')), jamais le rôle applicatif.
 *
 * Le compte est un utilisateur technique du tenant, sans mot de passe communiqué
 * (il ne se connecte jamais à l'UI) et sans autre droit que ces deux abilities.
 *
 * Idempotent : ré-exécuter la commande ne duplique jamais le compte et n'émet un
 * nouveau jeton que si --fresh est passé explicitement (sinon un jeton existant
 * reste valide et n'est pas révélé une seconde fois).
 */
class IssueAchatsAgentToken extends Command
{
    private const EMAIL = 'agent-ia.achats@jadema.o3app.local';
    private const TOKEN_NAME = 'agent-ia';
    private const ABILITIES = ['achats:import', 'ventes:whatsapp-import'];

    protected $signature = 'achats:agent-token
        {tenant=jadema : ID du tenant}
        {--fresh : révoque le(s) jeton(s) existant(s) et en émet un nouveau}';

    protected $description = "Crée (si besoin) le compte de service « Agent IA » et émet son jeton (achats + ventes)";

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' introuvable.");
            return self::FAILURE;
        }

        $exit = self::FAILURE;
        $tenant->run(function () use (&$exit) {
            $exit = $this->issue();
        });

        return $exit;
    }

    private function issue(): int
    {
        $role = Role::where('name', 'cashier')->first();

        $user = User::withTrashed()->firstOrNew(['email' => self::EMAIL]);
        $isNew = !$user->exists;

        $user->name = 'Agent IA — Jadema';
        $user->email = self::EMAIL;
        $user->is_active = true;
        // Pas de structure_id : BelongsToStructure ne l'auto-remplit que depuis
        // auth()->user(), inexistant en contexte console — il reste donc null,
        // ce qui convient à un compte technique sans agence/structure attachée.
        if ($isNew) {
            $user->password = Str::password(48); // jamais communiqué : ce compte ne se connecte pas à l'UI
        }
        if ($role) {
            $user->role_id = $role->id;
        }
        $user->save();
        if ($user->trashed()) {
            $user->restore();
        }

        $this->info($isNew ? "Compte créé : {$user->email}" : "Compte existant réutilisé : {$user->email}");

        $hasTokens = $user->tokens()->exists();
        if ($hasTokens && !$this->option('fresh')) {
            $this->warn("Un jeton existe déjà pour ce compte. Relancez avec --fresh pour le révoquer et en émettre un nouveau.");
            return self::SUCCESS;
        }

        if ($hasTokens) {
            $user->tokens()->delete();
            $this->warn('Ancien(s) jeton(s) révoqué(s).');
        }

        $token = $user->createToken(self::TOKEN_NAME, self::ABILITIES)->plainTextToken;

        $this->newLine();
        $this->line("<fg=black;bg=yellow> JETON (ne sera plus jamais affiché) </>");
        $this->line($token);
        $this->newLine();
        $this->info('Copiez-le maintenant dans le fichier .o3token (une seule ligne) de CHACUN des deux dossiers '
            . 'agents — "factures fournisseurs\importer\.o3token" et "facturation clients\importer\.o3token" — '
            . 'ou dans la variable d\'environnement O3_API_TOKEN si elle est partagée entre les deux.');

        return self::SUCCESS;
    }
}
