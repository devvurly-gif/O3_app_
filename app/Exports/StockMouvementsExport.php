<?php

namespace App\Exports;

use App\Models\StockMouvement;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class StockMouvementsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $q = StockMouvement::query()->with(['product', 'warehouse', 'user']);

        if (!empty($this->filters['direction'])) {
            $q->where('direction', $this->filters['direction']);
        }

        if (!empty($this->filters['reason'])) {
            $q->where('reason', $this->filters['reason']);
        }

        if (!empty($this->filters['product_id'])) {
            $q->where('product_id', $this->filters['product_id']);
        }

        if (!empty($this->filters['warehouse_id'])) {
            $q->where('warehouse_id', $this->filters['warehouse_id']);
        }

        return $q->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'ID', 'Date', 'Produit', 'Entrepôt',
            'Direction', 'Motif',
            'Quantité', 'Coût unitaire',
            'Stock avant', 'Stock après',
            'Réf. document', 'Type document',
            'Utilisateur', 'Notes',
        ];
    }

    public function map($m): array
    {
        return [
            $m->id,
            $m->created_at?->format('d/m/Y H:i'),
            $m->product?->p_title,
            $m->warehouse?->wh_title,
            $m->direction === 'in' ? 'Entrée' : 'Sortie',
            $m->reason,
            $m->quantity,
            $m->unit_cost,
            $m->stock_before,
            $m->stock_after,
            $m->document_reference,
            $m->document_type,
            $m->user?->name,
            $m->notes,
        ];
    }
}
