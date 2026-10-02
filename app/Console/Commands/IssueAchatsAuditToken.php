<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Émet le jeton de CONTRÔLE (lecture seule, ability "achats:audit") de l'agent achats, pour
 * GET /api/achats/audit. C'est un jeton distinct de celui d'import (achats:agent-token) :
 * le compte « Agent IA — Achats » doit déjà exister, et réémettre l'un ne révoque jamais
 * l'autre (--fresh ne touche que les jetons nommés « agent-audit »).
 */
class IssueAchatsAuditToken extends Command
{
    private const EMAIL = 'agent-ia.achats@jadema.o3app.local';
    private const TOKEN_NAME = 'agent-audit';
    private const ABILITY = 'achats:audit';

    protected $signature = 'achats:audit-token
        {tenant=jadema : ID du tenant}
        {--fresh : révoque l\'ancien jeton de contrôle et en émet un nouveau}';

    protected $description = "Émet le jeton de contrôle (lecture seule) de l'agent achats";

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (!$tenant) {
            $this->error("Tenant '{$this->argument('tenant')}' introuvable.");
            return self::FAILURE;
        }

        $exit = self::FAILURE;
        $tenant->run(function () use (&$exit) {
            $user = User::where('email', self::EMAIL)->first();
            if (!$user) {
                $this->error("Compte « Agent IA — Achats » absent : lancer d'abord achats:agent-token.");
                return;
            }

            $existing = $user->tokens()->where('name', self::TOKEN_NAME);
            if ($existing->exists() && !$this->option('fresh')) {
                $this->warn('Un jeton de contrôle existe déjà. Relancez avec --fresh pour le remplacer.');
                $exit = self::SUCCESS;
                return;
            }
            $existing->delete();

            $expiresAt = now()->addYear();
            $token = $user->createToken(self::TOKEN_NAME, [self::ABILITY], $expiresAt)->plainTextToken;

            $this->newLine();
            $this->line('<fg=black;bg=yellow> JETON DE CONTRÔLE (ne sera plus jamais affiché) </>');
            $this->line($token);
            $this->line('Valable jusqu\'au ' . $expiresAt->format('d/m/Y') . '.');
            $this->info('À copier dans "factures fournisseurs\importer\.o3audit" (une seule ligne). Lecture seule.');
            $exit = self::SUCCESS;
        });

        return $exit;
    }
}
