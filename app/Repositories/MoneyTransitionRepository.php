<?php

namespace App\Repositories;

use App\Models\MoneyTransition;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MoneyTransitionRepository extends BaseRepository
{
    public function __construct(MoneyTransition $model)
    {
        parent::__construct($model);
    }


    public function createRecurringTransaction(array $data): Collection
    {
        return DB::transaction(function () use ($data) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            if ($startDate->gt($endDate)) {
                throw new \InvalidArgumentException('Start date cannot be after end date');
            }
            $batchId = Str::uuid()->toString();
            $transactions = [];
            $currentDate = $startDate->copy();
            $createdCount = 0;
            $maxTransactions = 50;

            while ($currentDate->lte($endDate) && $createdCount < $maxTransactions) {
                $transactions[] = [
                    'id' => Str::uuid()->toString(),
                    'amount' => $data['amount'],
                    'description' => $data['description'],
                    'categorie_id' => $data['categorie_id'],
                    'user_id' => $data['user_id'],
                    'date' => $currentDate->format('Y-m-d'),
                    'occurence' => $data['occurence'],
                    'recurrence_batch_id' => $batchId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $createdCount++;
                switch ($data['occurence']) {
                    case 'weekly':
                        $currentDate->addWeek();
                        break;
                    case 'monthly':
                        $currentDate->addMonth();
                        break;
                    case 'yearly':
                        $currentDate->addYear();
                        break;
                }
            }
            $this->model->insert($transactions);
            $insertedModels = $this->model->where('recurrence_batch_id', $batchId)->get();

            return $insertedModels;
        });
    }

    public function query(array $filters = [], int $page = 10)
    {
        $query = MoneyTransition::query()->with('categorie');

        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            switch ($field) {
                case 'amount':
                    $query->where('amount', $value);
                    break;
                case 'categorie_id':
                    $query->where('categorie_id', $value);
                    break;
                case 'user_id':
                    $query->where('user_id', $value);
                    break;
                case 'categorie.is_income':
                    $query->whereHas('categorie', fn ($q) => $q->where('is_income', $value));
                    break;
                case 'categorie.parent_id':
                    $query->whereHas('categorie', fn ($q) => $q->where('parent_id', $value));
                    break;
                case 'date':
                    $query->whereDate('date', $value);
                    break;
                case 'start_date':
                    $query->whereDate('date', '>=', $value);
                    break;
                case 'end_date':
                    $query->whereDate('date', '<=', $value);
                    break;
                case 'max_amount':
                    $query->where('amount', '<=', $value);
                    break;
                case 'min_amount':
                    $query->where('amount', '>=', $value);
                    break;
                case 'categorie.name':
                    $query->whereHas(
                        'categorie',
                        fn ($q) => $q->where('name', 'like', '%'.$value.'%')
                    );
                    break;
                case 'type':
                    $query->whereHas(
                        'categorie',
                        fn ($q) => $q->where('is_income', $value)
                    );
                    break;
                case 'categories':
                    $query->whereHas(
                        'categorie',
                        fn ($q) => $q->whereIn('parent_id', (array) $value)
                    );
                    break;
            }
        }
        $query->orderBy('date', 'asc');
        if (!empty($page)) {
            return $query->paginate($page);
        }

        return $query->get();
    }
}
