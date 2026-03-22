<?php

namespace App\Repositories\Eloquent;

use App\Models\WarehouseTransfer;
use App\Repositories\Contracts\WarehouseTransferRepositoryInterface;

class WarehouseTransferRepository extends BaseRepository implements WarehouseTransferRepositoryInterface
{
    public function __construct(WarehouseTransfer $model)
    {
        parent::__construct($model);
    }
}
