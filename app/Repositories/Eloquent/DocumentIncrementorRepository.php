<?php

namespace App\Repositories\Eloquent;

use App\Models\DocumentIncrementor;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;

class DocumentIncrementorRepository extends BaseRepository implements DocumentIncrementorRepositoryInterface
{
    public function __construct(DocumentIncrementor $model)
    {
        parent::__construct($model);
    }

    public function incrementNextTrick(DocumentIncrementor $incrementor): void
    {
        $incrementor->increment('nextTrick');
    }

    public function findAndLock(int $id): DocumentIncrementor
    {
        return DocumentIncrementor::where('id', $id)->lockForUpdate()->firstOrFail();
    }

    public function findByModel(string $model): ?DocumentIncrementor
    {
        return DocumentIncrementor::where('di_model', $model)->first();
    }
}
