<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ProductsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $q = Product::query()->with(['category', 'brand']);

        if (!empty($this->filters['search'])) {
            $q->where(function ($q2) {
                $q2->where('p_title', 'like', '%' . $this->filters['search'] . '%')
                   ->orWhere('p_sku', 'like', '%' . $this->filters['search'] . '%');
            });
        }

        if (!empty($this->filters['category_id'])) {
            $q->where('category_id', $this->filters['category_id']);
        }

        if (isset($this->filters['p_status'])) {
            $q->where('p_status', $this->filters['p_status']);
        }

        return $q->orderBy('p_title');
    }

    public function headings(): array
    {
        return [
            'ID', 'Code', 'Titre', 'SKU', 'EAN13',
            'Catégorie', 'Marque',
            'Prix Achat', 'Prix Vente', 'Coût', 'TVA %',
            'Unité', 'Statut',
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->p_code,
            $product->p_title,
            $product->p_sku,
            $product->p_ean13,
            $product->category?->ctg_title,
            $product->brand?->br_title,
            $product->p_purchasePrice,
            $product->p_salePrice,
            $product->p_cost,
            $product->p_taxRate,
            $product->p_unit,
            $product->p_status ? 'Actif' : 'Inactif',
        ];
    }
}
