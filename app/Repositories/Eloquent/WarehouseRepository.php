<?php

namespace App\Repositories\Eloquent;

use App\Models\Warehouse;
use App\Repositories\Contracts\WarehouseRepositoryInterface;

class WarehouseRepository extends BaseRepository implements WarehouseRepositoryInterface
{
    public function __construct(Warehouse $model)
    {
        parent::__construct($model);
    }
}
