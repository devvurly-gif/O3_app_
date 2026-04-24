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

    /**
     * Sanitize an `order by` column+direction pair before passing it
     * to the query builder. Every controller that accepts `sort` /
     * `direction` from the request eventually lands here, so this is
     * the one chokepoint where user input hits `orderBy()`.
     *
     * SECURITY (H3):
     *   - direction: hard-lock to asc|desc (else fallback asc).
     *   - column:    only allow snake_case identifiers
     *                (optionally dotted for table.col relations).
     *                Anything else falls back to `id`.
     *
     * This is defense-in-depth; Laravel's grammar wraps the column
     * in backticks, but a crafted value with backticks or parens
     * could still leak schema or crash a query. No DB identifier we
     * actually sort on contains anything outside `[A-Za-z0-9_.]`.
     *
     * @return array{0:string,1:string}
     */
    protected function sanitizeOrder(string $orderBy, string $direction): array
    {
        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $orderBy)) {
            $orderBy = 'id';
        }
        return [$orderBy, $direction];
    }

    public function all(array $with = [], string $orderBy = 'id', string $direction = 'asc'): Collection
    {
        [$orderBy, $direction] = $this->sanitizeOrder($orderBy, $direction);
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

        [$orderBy, $direction] = $this->sanitizeOrder($orderBy, $direction);
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
