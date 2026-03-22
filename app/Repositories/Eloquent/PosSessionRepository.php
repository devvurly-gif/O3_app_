<?php

namespace App\Repositories\Eloquent;

use App\Models\PosSession;
use App\Repositories\Contracts\PosSessionRepositoryInterface;

class PosSessionRepository extends BaseRepository implements PosSessionRepositoryInterface
{
    public function __construct(PosSession $model)
    {
        parent::__construct($model);
    }
}
