<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentRoutingRule;
use App\Models\AgentThreshold;
use Illuminate\Database\Seeder;

/**
 * Valeurs de départ du socle des agents IA (spécification, sections 4 et 5).
 * Lancé à la main par tenant : php artisan tenants:run db:seed --option="class=AgentFoundationSeeder".
 * Les seuils sont des points de départ à recalibrer ; value null = à définir.
 */
class AgentFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            'achats' => 'Achats & catalogue', 'ventes' => 'Ventes & CRM', 'stocks' => 'Stocks',
            'expedition' => 'Expédition', 'recouvrement' => 'Recouvrement',
            'comptabilite' => 'Comptabilité', 'marketing' => 'Marketing',
        ];
        foreach ($agents as $domain => $name) {
            Agent::firstOrCreate(['domain' => $domain], ['name' => $name, 'is_active' => in_array($domain, ['achats', 'stocks', 'ventes', 'expedition'], true)]);
        }

        // [type, conditions, domaine, priorité, phase]
        $rules = [
            ['facture_fournisseur', ['source' => 'pdf', 'keywords' => ['facture']], 'achats', 100, 1],
            ['bon_livraison_fournisseur', ['source' => 'pdf', 'keywords' => ['bon de livraison']], 'achats', 110, 1],
            ['alerte_stock', ['source' => 'erp'], 'stocks', 100, 1],
            ['produit_dormant', ['source' => 'erp'], 'stocks', 90, 1],
            ['inventaire_demande', ['source' => 'manual', 'typed_only' => true], 'stocks', 100, 1],
            ['demande_devis', ['source' => ['whatsapp', 'sms'], 'keywords' => ['devis', 'prix', 'je veux']], 'ventes', 100, 2],
            ['commande_creee', ['source' => ['whatsapp', 'sms'], 'typed_only' => true], 'ventes', 100, 2],
            ['commande_confirmee', ['source' => 'erp'], 'stocks', 100, 2],
            ['suivi_colis', ['source' => ['whatsapp', 'sms'], 'keywords' => ['ou est ma commande', 'où est ma commande', 'suivi']], 'expedition', 120, 2],
            ['controle_encaissements', ['source' => 'manual', 'typed_only' => true], 'recouvrement', 100, 1],
            ['virement_recu', ['source' => 'bank'], 'recouvrement', 100, 3],
            ['echeance_depassee', ['source' => 'erp'], 'recouvrement', 100, 3],
            ['ecriture_a_passer', ['source' => 'erp'], 'comptabilite', 100, 4],
            ['campagne_demandee', ['source' => 'manual'], 'marketing', 100, 4],
        ];
        foreach ($rules as [$type, $conditions, $domain, $priority, $phase]) {
            AgentRoutingRule::firstOrCreate(
                ['event_type' => $type, 'agent_domain' => $domain],
                ['conditions' => $conditions, 'priority' => $priority, 'phase' => $phase, 'is_active' => $phase <= 2],
            );
        }

        // [domaine, type d'action, paramètre, valeur, unité]
        $thresholds = [
            ['achats', 'facture_fournisseur', 'price_gap_pct', 5, '%'],
            ['achats', 'commande_fournisseur', 'amount', 20000, 'MAD'],
            ['ventes', 'devis', 'discount_pct', 15, '%'],
            ['ventes', 'devis', 'margin_pct', null, '%'],
            ['expedition', 'livraison_cod', 'amount', null, 'MAD'],
            ['recouvrement', 'relance', 'reminder_level', 2, null],
            ['recouvrement', 'relance', 'days_level1', 1, 'j'],
            ['recouvrement', 'relance', 'days_level2', 15, 'j'],
            ['recouvrement', 'relance', 'days_level3', 30, 'j'],
        ];
        foreach ($thresholds as [$domain, $action, $param, $value, $unit]) {
            AgentThreshold::firstOrCreate(
                ['agent_domain' => $domain, 'action_type' => $action, 'parameter' => $param],
                ['value' => $value, 'unit' => $unit],
            );
        }
    }
}
