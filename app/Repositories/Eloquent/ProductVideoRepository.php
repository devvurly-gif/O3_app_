<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Models\ProductVideo;
use App\Repositories\Contracts\ProductVideoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductVideoRepository extends BaseRepository implements ProductVideoRepositoryInterface
{
    public function __construct(ProductVideo $model)
    {
        parent::__construct($model);
    }

    public function allForProduct(Product $product): Collection
    {
        return $product->videos;
    }

    public function createForProduct(Product $product, array $data): Model
    {
        return $product->videos()->create($data);
    }
}
