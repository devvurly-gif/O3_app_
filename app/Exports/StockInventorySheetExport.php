<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Feuille d'inventaire préparée par l'agent Stocks : à imprimer ou à remplir.
 * Les colonnes « Quantité comptée » et « Commentaire » sont vides ; « Écart »
 * se calcule dès qu'une quantité est saisie. Aucun stock n'est modifié.
 */
class StockInventorySheetExport implements FromArray, WithHeadings, ShouldAutoSize
{
    use Exportable;

    public const HEADINGS = [
        'Entrepôt', 'SKU', 'Désignation', 'Catégorie', 'Stock théorique',
        'Mouvements en attente', 'Dernier mouvement', 'À vérifier',
        'Quantité comptée', 'Écart', 'Commentaire',
    ];

    /** @param array<int, array<int, mixed>> $rows une ligne par article, dans l'ordre de HEADINGS (sans Écart) */
    public function __construct(private array $rows)
    {
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    public function array(): array
    {
        // Écart = comptée − théorique, vide tant que rien n'est compté. Ligne 1 = en-têtes.
        return array_map(function (array $row, int $i) {
            $line = $i + 2;
            array_splice($row, 9, 0, ["=IF(I{$line}=\"\",\"\",I{$line}-E{$line})"]);

            return $row;
        }, $this->rows, array_keys($this->rows));
    }
}
