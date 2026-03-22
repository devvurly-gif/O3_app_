<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ProductImageRepositoryInterface extends BaseRepositoryInterface
{
    public function allForProduct(Product $product): Collection;

    public function createForProduct(Product $product, array $data): Model;

    public function clearPrimary(Product $product): void;

    public function setPrimary(Product $product, ProductImage $image): Model;

    public function countForProduct(Product $product): int;
}
