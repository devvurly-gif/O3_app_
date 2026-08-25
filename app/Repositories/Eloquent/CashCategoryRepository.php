<?php

namespace App\Repositories\Eloquent;

use App\Models\CashCategory;
use App\Repositories\Contracts\CashCategoryRepositoryInterface;

class CashCategoryRepository extends BaseRepository implements CashCategoryRepositoryInterface
{
    public function __construct(CashCategory $model)
    {
        parent::__construct($model);
    }
}
