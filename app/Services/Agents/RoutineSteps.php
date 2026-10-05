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
