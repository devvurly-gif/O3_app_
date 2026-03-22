<?php

namespace App\Repositories\Eloquent;

use App\Models\StructureIncrementor;
use App\Repositories\Contracts\StructureIncrementorRepositoryInterface;

class StructureIncrementorRepository extends BaseRepository implements StructureIncrementorRepositoryInterface
{
    public function __construct(StructureIncrementor $model)
    {
        parent::__construct($model);
    }
}
