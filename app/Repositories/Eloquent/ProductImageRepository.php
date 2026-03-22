<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Models\ProductImage;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductImageRepository extends BaseRepository implements ProductImageRepositoryInterface
{
    public function __construct(ProductImage $model)
    {
        parent::__construct($model);
    }

    public function allForProduct(Product $product): Collection
    {
        return $product->images;
    }

    public function createForProduct(Product $product, array $data): Model
    {
        return $product->images()->create($data);
    }

    public function clearPrimary(Product $product): void
    {
        $product->images()->update(['isPrimary' => false]);
    }

    public function setPrimary(Product $product, ProductImage $image): Model
    {
        $this->clearPrimary($product);
        $image->update(['isPrimary' => true]);
        return $image;
    }

    public function countForProduct(Product $product): int
    {
        return $product->images()->count();
    }
}
