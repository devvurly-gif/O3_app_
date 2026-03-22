<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\Importable;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    use Importable;

    private int $created = 0;
    private int $updated = 0;

    public function model(array $row)
    {
        $category = !empty($row['categorie'])
            ? Category::where('ctg_title', $row['categorie'])->first()
            : null;

        $brand = !empty($row['marque'])
            ? Brand::where('br_title', $row['marque'])->first()
            : null;

        $existing = !empty($row['sku'])
            ? Product::where('p_sku', $row['sku'])->first()
            : null;

        if ($existing) {
            $existing->update(array_filter([
                'p_title'         => $row['titre'] ?? null,
                'p_purchasePrice' => $row['prix_achat'] ?? null,
                'p_salePrice'     => $row['prix_vente'] ?? null,
                'p_cost'          => $row['cout'] ?? null,
                'p_taxRate'       => $row['tva'] ?? null,
                'p_unit'          => $row['unite'] ?? null,
                'category_id'     => $category?->id,
                'brand_id'        => $brand?->id,
            ]));
            $this->updated++;
            return null;
        }

        $this->created++;
        return new Product([
            'p_title'         => $row['titre'],
            'p_sku'           => $row['sku'] ?? null,
            'p_ean13'         => $row['ean13'] ?? null,
            'p_purchasePrice' => $row['prix_achat'] ?? 0,
            'p_salePrice'     => $row['prix_vente'] ?? 0,
            'p_cost'          => $row['cout'] ?? 0,
            'p_taxRate'       => $row['tva'] ?? 20,
            'p_unit'          => $row['unite'] ?? 'pcs',
            'p_status'        => true,
            'category_id'     => $category?->id,
            'brand_id'        => $brand?->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'sku'   => ['nullable', 'string', 'max:100'],
        ];
    }

    public function getCreatedCount(): int { return $this->created; }
    public function getUpdatedCount(): int { return $this->updated; }
}
