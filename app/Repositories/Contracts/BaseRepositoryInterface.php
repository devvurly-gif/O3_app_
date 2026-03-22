<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    public function all(array $with = [], string $orderBy = 'id', string $direction = 'asc'): Collection;

    public function paginate(
        int $perPage = 15,
        array $with = [],
        string $orderBy = 'id',
        string $direction = 'asc',
        array $filters = []
    ): LengthAwarePaginator;

    public function find(int $id, array $with = []): Model;

    public function create(array $data): Model;

    public function update(Model $model, array $data): Model;

    public function delete(Model $model): bool;
}
