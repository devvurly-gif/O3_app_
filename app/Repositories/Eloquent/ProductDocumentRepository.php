<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Models\ProductDocument;
use App\Repositories\Contracts\ProductDocumentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductDocumentRepository extends BaseRepository implements ProductDocumentRepositoryInterface
{
    public function __construct(ProductDocument $model)
    {
        parent::__construct($model);
    }

    public function allForProduct(Product $product): Collection
    {
        return $product->documents;
    }

    public function createForProduct(Product $product, array $data): Model
    {
        return $product->documents()->create($data);
    }
}
