<?php

namespace App\Repositories\Eloquent;

use App\Models\StockMouvement;
use App\Repositories\Contracts\StockMouvementRepositoryInterface;

class StockMouvementRepository extends BaseRepository implements StockMouvementRepositoryInterface
{
    public function __construct(StockMouvement $model)
    {
        parent::__construct($model);
    }
}
