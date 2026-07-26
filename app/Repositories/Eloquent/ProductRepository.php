<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    /**
     * Override paginate to handle special filters that don't map to a plain
     * column comparison:
     *   in_stock  = true  → only products with at least one warehouse
     *                       whose stockLevel > 0
     *             = false → only products with NO warehouse stock > 0
     *                       (rupture: zero everywhere or no warehouse row at all)
     *   on_promo  = true  → only products with a currently-active promotion
     *             = false → only products with no currently-active promotion
     *
     * All other filters fall through to BaseRepository::paginate.
     */
    public function paginate(
        int $perPage = 15,
        array $with = [],
        string $orderBy = 'id',
        string $direction = 'asc',
        array $filters = []
    ): LengthAwarePaginator {
        $inStock = $filters['in_stock'] ?? null;
        unset($filters['in_stock']);

        $onPromo = $filters['on_promo'] ?? null;
        unset($filters['on_promo']);

        // Build the standard query via the parent helper but capture it via
        // a temporary override: we re-implement the foreach to keep the
        // whereHas in the same chain.
        $query = $this->model->with($with);

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') continue;

            if ($key === 'search' && is_array($value) && !empty($value['value'])) {
                $query->where(function ($q) use ($value) {
                    foreach ($value['columns'] as $col) {
                        $q->orWhere($col, 'like', '%' . $value['value'] . '%');
                    }
                });
                continue;
            }

            if (str_ends_with($key, ':like')) {
                $query->where(rtrim($key, ':like'), 'like', '%' . $value . '%');
                continue;
            }

            $query->where($key, $value);
        }

        if ($inStock === true) {
            $query->whereHas('warehouseStocks', fn ($q) => $q->where('stockLevel', '>', 0));
        } elseif ($inStock === false) {
            $query->whereDoesntHave('warehouseStocks', fn ($q) => $q->where('stockLevel', '>', 0));
        }

        if ($onPromo === true) {
            $query->whereHas('promotions', fn ($q) => $q->active());
        } elseif ($onPromo === false) {
            $query->whereDoesntHave('promotions', fn ($q) => $q->active());
        }

        return $query->orderBy($orderBy, $direction)->paginate($perPage);
    }
}
