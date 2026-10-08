<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Reprise des tenants qui utilisent déjà les agents IA — À LIRE AVANT DE DÉPLOYER.
 *
 * L'option « Agents IA » est désormais ÉTEINTE par défaut : elle s'allume depuis la gestion des tenants, après paiement
 * d'une formule Pro ou Business, et c'est cet acte qui installe le socle des agents. Sans cette reprise, un client qui s'en
 * sert déjà (le socle est installé dans sa base) perdrait l'accès à l'instant du déploiement.
 *
 * Elle allume donc l'option pour les tenants dont la base contient déjà des agents. Rien n'est installé ni supprimé ; un
 * tenant sans agents reste éteint et suivra la procédure normale. Une base injoignable est journalisée et ignorée.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Tenant::all() as $tenant) {
            try {
                $uses = $tenant->run(fn () => Schema::hasTable('agents') && DB::table('agents')->exists());
            } catch (\Throwable $e) {
                Log::warning("Agents IA : tenant {$tenant->id} ignoré par la reprise ({$e->getMessage()})");

                continue;
            }

            if ($uses && !$tenant->agentsEnabled()) {
                $tenant->agents_enabled = true;
                $tenant->save();
            }
        }
    }

    public function down(): void
    {
        // Rien à défaire : l'option reste telle que le super-administrateur l'a réglée.
    }
};
