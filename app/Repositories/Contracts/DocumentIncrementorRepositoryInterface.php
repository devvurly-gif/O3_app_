<?php

namespace App\Repositories\Contracts;

use App\Models\DocumentIncrementor;

interface DocumentIncrementorRepositoryInterface extends BaseRepositoryInterface
{
    public function incrementNextTrick(DocumentIncrementor $incrementor): void;

    public function findAndLock(int $id): DocumentIncrementor;

    public function findByModel(string $model): ?DocumentIncrementor;
}
