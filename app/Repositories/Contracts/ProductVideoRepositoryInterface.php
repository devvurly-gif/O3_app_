<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ProductVideoRepositoryInterface extends BaseRepositoryInterface
{
    public function allForProduct(Product $product): Collection;

    public function createForProduct(Product $product, array $data): Model;
}
