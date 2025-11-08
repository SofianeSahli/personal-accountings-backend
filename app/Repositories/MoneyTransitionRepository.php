<?php

namespace App\Repositories;

use App\Models\MoneyTransition;

class MoneyTransitionRepository extends BaseRepository
{
    public function __construct(MoneyTransition $model)
    {
        parent::__construct($model);
    }
}
