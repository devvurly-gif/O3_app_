<?php

namespace App\Repositories\Eloquent;

use App\Models\CashTransaction;
use App\Repositories\Contracts\CashTransactionRepositoryInterface;

class CashTransactionRepository extends BaseRepository implements CashTransactionRepositoryInterface
{
    public function __construct(CashTransaction $model)
    {
        parent::__construct($model);
    }
}
