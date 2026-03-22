<?php

namespace App\Repositories\Eloquent;

use App\Models\DocumentHeader;
use App\Repositories\Contracts\DocumentHeaderRepositoryInterface;

class DocumentHeaderRepository extends BaseRepository implements DocumentHeaderRepositoryInterface
{
    public function __construct(DocumentHeader $model)
    {
        parent::__construct($model);
    }
}
