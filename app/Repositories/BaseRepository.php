<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

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

    public function findByPK(string|int $id, array $columns = ['*']): ?Model
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

    public function update(string|int $id, array $data): ?Model
    {
        $model = $this->findByPK($id);
        if ($model) {
            $model->update($data);
        }
        return $model;
    }

    public function delete(string|int $id): bool
    {
        $model = $this->findByPK($id);
        return $model ? (bool) $model->delete() : false;
    }

    public function query(array $filters = []): Collection
    {
        $query = $this->model->newQuery();

        foreach ($filters as $field => $value) {
            $query->where($field, $value);
        }

        return $query->get();
    }
}
