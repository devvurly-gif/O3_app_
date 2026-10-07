<?php

namespace App\Services\Agents;

use App\Models\Agent;

/**
 * Les étapes qu'une routine planifiée a le droit d'enchaîner, sans personne devant l'écran.
 *
 * Seulement des étapes qui LISENT ou préparent un BROUILLON : une routine ne peut jamais appliquer quoi que ce
 * soit (« applique… » n'y figure pas) ni écrire un document. Chaque étape est une demande que l'orchestrateur
 * comprend déjà, dans sa forme canonique. Une étape « agent:N » lance un agent recruté (lecture seule).
 */
class RoutineSteps
{
    /** @var array<string, array{label: string, phrase: string, does: string}> */
    public const KNOWN = [
        'etat'          => ['label' => 'État des agents', 'phrase' => 'état des agents', 'does' => 'la situation des agents, des événements et des validations en attente'],
        'a_trier'       => ['label' => 'Événements à trier', 'phrase' => 'événements à trier', 'does' => 'les messages que le routeur n\'a pas su confier'],
        'inventaire'    => ['label' => 'Préparer un inventaire', 'phrase' => 'prépare un inventaire', 'does' => 'une feuille de comptage en brouillon (aucun stock modifié)'],
        'encaissements' => ['label' => 'Contrôler les encaissements', 'phrase' => 'contrôle les encaissements', 'does' => 'des relances de paiement en brouillon (aucun client contacté)'],
        'relances'      => ['label' => 'Relances à valider', 'phrase' => 'relances à valider', 'does' => 'ce qui attend votre validation'],
        'photos'        => ['label' => 'Chercher les photos Jadever', 'phrase' => 'cherche les photos Jadever', 'does' => 'les photos officielles des produits Jadever sans photo, en aperçu à valider (aucune photo rattachée)'],
        'point'         => ['label' => 'Point de la journée', 'phrase' => 'résume la journée', 'does' => 'ventes, encaissements, factures échues, stock bas et validations en attente (lecture)'],
        'validations'   => ['label' => 'Ce qui attend une validation', 'phrase' => 'que dois-je valider ?', 'does' => 'les propositions et relances en attente (lecture)'],
        'ventes_hier'   => ['label' => "Ventes d'hier", 'phrase' => "ventes d'hier", 'does' => "le chiffre d'affaires de la veille, ses principaux clients et produits (lecture)"],
        'encaissements_hier' => ['label' => "Encaissements d'hier", 'phrase' => "encaissements d'hier", 'does' => 'les paiements de la veille par mode (lecture)'],
        'echues'        => ['label' => 'Factures échues', 'phrase' => 'factures échues', 'does' => 'les factures de vente en retard de paiement (lecture)'],
        'devis'         => ['label' => 'Devis sans suite', 'phrase' => 'devis sans suite depuis 10 jours', 'does' => 'les devis ouverts depuis plus de 10 jours (lecture)'],
        'bl_non_factures' => ['label' => 'Bons de livraison non facturés', 'phrase' => 'bons de livraison non facturés', 'does' => 'les bons livrés et pas encore facturés (lecture)'],
        'fournisseurs_a_payer' => ['label' => 'Factures fournisseurs à payer', 'phrase' => 'factures fournisseurs à payer cette semaine', 'does' => 'les échéances fournisseurs des 7 prochains jours (lecture)'],
        'sessions_caisse' => ['label' => 'Sessions de caisse', 'phrase' => 'sessions de caisse', 'does' => 'sessions oubliées, à valider, écarts du mois (lecture)'],
        'devis_relance' => ['label' => 'Préparer les relances de devis', 'phrase' => 'relance les devis sans réponse', 'does' => 'les messages de relance des devis ouverts depuis plus de 10 jours, à valider (rien n\'est envoyé)'],
        'versements_retard' => ['label' => 'Relancer les versements en retard', 'phrase' => 'relance les versements en retard', 'does' => 'les messages de relance des versements d\'échéanciers en retard, à valider (rien n\'est envoyé)'],
        'fiches'        => ['label' => 'Contrôler les fiches produits', 'phrase' => 'mettre à jour les fiches produits', 'does' => 'le contrôle des fiches (lecture seule)'],
    ];

    /**
     * Ne garde que des étapes autorisées. @param array<int, mixed> $steps
     * @return array<int, string>
     */
    public static function sanitize(array $steps): array
    {
        $out = [];
        foreach (array_slice($steps, 0, 6) as $step) {
            if (!is_string($step)) {
                continue;
            }
            $step = trim($step);
            if (isset(self::KNOWN[$step]) || (preg_match('/^agent:(\d+)$/', $step, $m) && Agent::where('kind', 'custom')->whereKey((int) $m[1])->exists())) {
                $out[$step] = $step;
            }
        }

        return array_values($out);
    }

    /** « Contrôler les encaissements », « Agent Veille stock ». */
    public static function label(string $step): string
    {
        if (isset(self::KNOWN[$step])) {
            return self::KNOWN[$step]['label'];
        }
        if (preg_match('/^agent:(\d+)$/', $step, $m)) {
            $agent = Agent::find((int) $m[1]);

            return 'Agent ' . ($agent?->name ?? "#{$m[1]}");
        }

        return $step;
    }
}
