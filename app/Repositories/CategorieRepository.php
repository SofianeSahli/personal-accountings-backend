<?php

namespace App\Repositories;

use App\Models\Categorie;
use Illuminate\Database\Eloquent\Collection;

class CategorieRepository extends BaseRepository
{
    public function __construct(Categorie $model)
    {
        parent::__construct($model);
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->model->newQuery()
            ->whereNull('parent_id')
            ->with('children')
            ->get($columns)
        ;
    }
}
