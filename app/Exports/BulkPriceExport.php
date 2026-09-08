<?php

namespace App\Exports;

use App\Services\BulkSalePriceUpdater;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Le chiffrage d'une revision de prix, au format tableur.
 *
 * L'ecran n'affiche que les cinquante premieres lignes ; la feuille porte le
 * lot entier, c'est tout son interet — on l'ouvre pour trier, filtrer, et
 * discuter la grille avant de l'appliquer.
 *
 * Les lignes viennent de BulkSalePriceUpdater::rows(), la meme source que le
 * chiffrage a l'ecran : un chiffre lu ici et le meme chiffre lu la-bas ne
 * peuvent pas diverger.
 */
class BulkPriceExport implements FromGenerator, WithHeadings, ShouldAutoSize, WithStyles
{
    use Exportable;

    public function __construct(
        private readonly BulkSalePriceUpdater $updater,
        private readonly array $filters,
        private readonly array $rule,
        /** False retire les colonnes de cout pour qui n'a pas products.view_cost. */
        private readonly bool $withCosts = false,
    ) {
    }

    public function generator(): \Generator
    {
        foreach ($this->updater->rows($this->filters, $this->rule, $this->withCosts) as $row) {
            $line = [
                $row['p_code'],
                $row['p_title'],
                $row['stock'],
            ];

            if ($this->withCosts) {
                $line[] = $row['purchase'];
                $line[] = $row['cost'];
            }

            $line[] = $row['current'];
            // Une base a zero laisse le prix en l'etat : la cellule reste vide
            // plutot que d'afficher un zero qu'on pourrait prendre pour un prix.
            $line[] = $row['skipped'] ? null : $row['new'];
            $line[] = $row['skipped'] ? null : $row['delta'];

            if ($this->withCosts) {
                $line[] = $row['margin_before'];
                $line[] = $row['margin'];
            }

            $line[] = $this->statusOf($row);

            yield $line;
        }
    }

    public function headings(): array
    {
        $headings = ['Code', 'Produit', 'Stock'];

        if ($this->withCosts) {
            $headings[] = "Prix d'achat";
            $headings[] = 'Coût de revient';
        }

        $headings[] = 'Prix actuel';
        $headings[] = 'Nouveau prix';
        $headings[] = 'Écart';

        if ($this->withCosts) {
            $headings[] = 'Marge avant (%)';
            $headings[] = 'Marge après (%)';
        }

        $headings[] = 'Statut';

        return $headings;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /**
     * Ce que la ligne devient, en clair — c'est la colonne qu'on trie pour
     * isoler les cas a regarder.
     */
    private function statusOf(array $row): string
    {
        if ($row['skipped']) {
            return 'Ignoré — base à zéro';
        }

        if ($row['new'] < 0) {
            return 'Prix négatif — bloque l\'opération';
        }

        if (!$row['changed']) {
            return 'Inchangé';
        }

        return $row['below_purchase'] ? 'Sous le prix d\'achat' : 'À modifier';
    }
}
