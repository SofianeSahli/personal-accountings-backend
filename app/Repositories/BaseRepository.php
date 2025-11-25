<?php

namespace App\Repositories;

use App\Pagination\CustomLengthAwarePaginator;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->model->newQuery()->get($columns);
    }

    public function findByPK(int|string $id, array $columns = ['*']): ?Model
    {
        return $this->model->newQuery()->select($columns)->find($id);
    }

    public function findBy(array $criteria, array $columns = ['*']): ?Model
    {
        $query = $this->model->newQuery();
        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->select($columns)->first();
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(int|string $id, array $data): ?Model
    {
        $model = $this->findByPK($id);
        if ($model) {
            $model->update($data);
        }

        return $model;
    }

    public function delete(int|string $id): bool
    {
        $model = $this->findByPK($id);

        return $model ? (bool) $model->delete() : false;
    }

    public function query(array $filters = [], int $page = 10)
    {
        $query = $this->model->newQuery();
        $relations = [];

        foreach ($filters as $field => $value) {
            if (str_contains($field, '.')) {
                [$relation, $column] = explode('.', $field);
                array_push($relations, $relation);
                $query->whereHas($relation, function (Builder $q) use ($column, $value) {
                    $q->where($column, $value);
                });
            } else {
                $query->where($field, $value);
            }
        }

        if (empty($page)) {
            return $query->with($relations);
        }

        $items = $query->with($relations)->orderBy('created_at', 'desc')->paginate($page);
        return new CustomLengthAwarePaginator(
            $items->items(),
            $items->total(),
            $items->perPage(),
            $items->currentPage(),
            [
                'path' => $items->path(),
                'query' => request()->query(),
            ]
        );
    }
}
