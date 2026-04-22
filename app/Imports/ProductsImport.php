<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;

/**
 * Product import.
 *
 * Accepted heading row (order is free; missing columns are ignored):
 *   titre, sku, ean13, imei, categorie, marque, description, notes,
 *   prix_achat, prix_vente, prix_comptoir, prix_revendeur, prix_grossiste,
 *   quantite_dp, cout, tva, unite
 *
 * Behaviour:
 * - titre is required.
 * - sku, when present, is used as the upsert key on Product.p_sku.
 * - categorie / marque are upserted by title (auto-created if missing).
 * - prix_comptoir / prix_revendeur / prix_grossiste are synced as
 *   PriceListItem rows (min_qty=1) on PriceList records named
 *   "Comptoir" / "Revendeur" / "Grossiste" (auto-created).
 * - p_salePrice falls back to prix_vente, else prix_comptoir, else 0.
 * - quantite_dp is written to WarehouseHasStock for the "main" warehouse
 *   (wh_code='DP' preferred; then any warehouse whose title matches
 *   "principal" / "Dépôt"; then the first active warehouse).
 */
class ProductsImport implements OnEachRow, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    use Importable;

    private int $created = 0;
    private int $updated = 0;

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        // Drop completely empty rows defensively (SkipsEmptyRows handles most).
        if (empty(array_filter($data, fn ($v) => $v !== null && $v !== ''))) {
            return;
        }

        $category = $this->resolveCategory($data['categorie'] ?? null);
        $brand    = $this->resolveBrand($data['marque'] ?? null);

        $salePrice = $this->num($data['prix_vente'] ?? null)
            ?? $this->num($data['prix_comptoir'] ?? null)
            ?? 0.0;

        $attrs = array_filter([
            'p_title'         => $data['titre']       ?? null,
            'p_ean13'         => $data['ean13']       ?? null,
            'p_imei'          => $data['imei']        ?? null,
            'p_description'   => $data['description'] ?? null,
            'p_notes'         => $data['notes']       ?? null,
            'p_purchasePrice' => $this->num($data['prix_achat'] ?? null),
            'p_salePrice'     => $salePrice,
            'p_cost'          => $this->num($data['cout'] ?? null),
            'p_taxRate'       => $this->num($data['tva']  ?? null),
            'p_unit'          => $data['unite']       ?? null,
            'category_id'     => $category?->id,
            'brand_id'        => $brand?->id,
        ], fn ($v) => $v !== null && $v !== '');

        $existing = !empty($data['sku'])
            ? Product::where('p_sku', $data['sku'])->first()
            : null;

        if ($existing) {
            $existing->update($attrs);
            $product = $existing->fresh();
            $this->updated++;
        } else {
            $attrs['p_sku']           = $data['sku'] ?? null;
            $attrs['p_status']        = true;
            $attrs['p_taxRate']     ??= 20;
            $attrs['p_unit']        ??= 'pcs';
            $attrs['p_purchasePrice'] ??= 0;
            $attrs['p_cost']        ??= 0;
            $product = Product::create($attrs);
            $this->created++;
        }

        $taxRate = (float) ($product->p_taxRate ?? 20);
        $this->syncPriceTier($product->id, 'Comptoir',  $data['prix_comptoir']  ?? null, $taxRate);
        $this->syncPriceTier($product->id, 'Revendeur', $data['prix_revendeur'] ?? null, $taxRate);
        $this->syncPriceTier($product->id, 'Grossiste', $data['prix_grossiste'] ?? null, $taxRate);

        if (array_key_exists('quantite_dp', $data) && $data['quantite_dp'] !== null && $data['quantite_dp'] !== '') {
            $this->syncMainWarehouseStock($product->id, (float) $this->num($data['quantite_dp']));
        }
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'sku'   => ['nullable', 'string', 'max:100'],
        ];
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        // Accept "1 234,56" / "1234.56" / "1.234,56" style.
        $s = trim((string) $v);
        $s = str_replace([' ', "\xc2\xa0"], '', $s);
        // If both separators present, assume comma is decimal.
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? (float) $s : null;
    }

    private function resolveCategory(?string $name): ?Category
    {
        if (empty(trim((string) $name))) {
            return null;
        }
        return Category::firstOrCreate(['ctg_title' => trim($name)]);
    }

    private function resolveBrand(?string $name): ?Brand
    {
        if (empty(trim((string) $name))) {
            return null;
        }
        return Brand::firstOrCreate(['br_title' => trim($name)]);
    }

    private function syncPriceTier(int $productId, string $listName, mixed $priceHt, float $taxRate): void
    {
        $price = $this->num($priceHt);
        if ($price === null || $price <= 0) {
            return;
        }

        $list = PriceList::firstOrCreate(
            ['name' => $listName],
            ['is_active' => true, 'priority' => 0]
        );

        PriceListItem::updateOrCreate(
            [
                'price_list_id' => $list->id,
                'product_id'    => $productId,
                'min_qty'       => 1,
            ],
            [
                'price_ht'  => $price,
                'price_ttc' => round($price * (1 + $taxRate / 100), 2),
            ]
        );
    }

    private function syncMainWarehouseStock(int $productId, float $qty): void
    {
        $warehouse = Warehouse::where('wh_code', 'DP')->first()
            ?? Warehouse::where('wh_title', 'like', '%principal%')->first()
            ?? Warehouse::where('wh_title', 'like', '%Dépôt%')->first()
            ?? Warehouse::where('wh_status', true)->orderBy('id')->first();

        if (!$warehouse) {
            return;
        }

        WarehouseHasStock::updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'product_id' => $productId],
            ['stockLevel' => $qty, 'stockAtTime' => now()]
        );
    }

    public function getCreatedCount(): int { return $this->created; }
    public function getUpdatedCount(): int { return $this->updated; }
}
