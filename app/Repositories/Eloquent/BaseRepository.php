<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(protected Model $model)
    {
    }

    public function all(array $with = [], string $orderBy = 'id', string $direction = 'asc'): Collection
    {
        return $this->model->with($with)->orderBy($orderBy, $direction)->get();
    }

    /**
     * Paginate results with optional eager-loading, ordering and simple filters.
     *
     * $filters supports:
     *   'search'          => ['columns' => [...], 'value' => '...']
     *   'column_name'     => exact-match value
     *   'column_name:like' => LIKE value
     */
    public function paginate(
        int $perPage = 15,
        array $with = [],
        string $orderBy = 'id',
        string $direction = 'asc',
        array $filters = []
    ): LengthAwarePaginator {
        $query = $this->model->with($with);

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') continue;

            if ($key === 'search' && is_array($value) && !empty($value['value'])) {
                $query->where(function ($q) use ($value) {
                    foreach ($value['columns'] as $col) {
                        $q->orWhere($col, 'like', '%' . $value['value'] . '%');
                    }
                });
                continue;
            }

            if (str_ends_with($key, ':like')) {
                $query->where(rtrim($key, ':like'), 'like', '%' . $value . '%');
                continue;
            }

            $query->where($key, $value);
        }

        return $query->orderBy($orderBy, $direction)->paginate($perPage);
    }

    public function find(int $id, array $with = []): Model
    {
        return $this->model->with($with)->findOrFail($id);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);
        return $model;
    }

    public function delete(Model $model): bool
    {
        return $model->delete();
    }
}
