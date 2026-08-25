<?php

namespace App\Repositories\Eloquent;

use App\Models\CashAccount;
use App\Repositories\Contracts\CashAccountRepositoryInterface;

class CashAccountRepository extends BaseRepository implements CashAccountRepositoryInterface
{
    public function __construct(CashAccount $model)
    {
        parent::__construct($model);
    }
}
