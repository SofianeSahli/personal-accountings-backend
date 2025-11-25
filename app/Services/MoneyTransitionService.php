<?php

namespace App\Services;

use App\Repositories\MoneyTransitionRepository;

class MoneyTransitionService implements BaseService
{
    /**
     * @var MoneyTransitionRepository
     */
    private $repo;

    public function __construct(MoneyTransitionRepository $repo)
    {
        $this->repo = $repo;
    }


    public function create(array $data)
    {
        // For one-time transactions, create single record
        if ($data['occurence'] === 'one_time') {
            return $this->repo->create($data);
        }

        // For recurring transactions, handle in service method
        return $this->repo->createRecurringTransaction($data);
    }

    public function update($id, $data)
    {
        return $this->repo->update($id, $data);
    }

    public function delete($id): bool
    {
        return $this->repo->delete($id);
    }

    public function get($id)
    {
        return $this->repo->findByPK($id);
    }

    public function query($params = [], int $page = 10)
    {
        return $this->repo->query($params, $page);
    }

    public function summary($params)
    {
        $income = $this->repo->query($params + ['categorie.is_income' => 1], 0);
        $expenses = $this->repo->query($params + ['categorie.is_income' => 0], 0);
        $savings = $this->repo->query($params + ['categorie.name' => 'savings'], 0);
        $dailyIncome = $this->groupByDay($income);
        $dailyExpenses = $this->groupByDay($expenses);
        $dailySavings = $this->groupByDay($savings);
        return [
            'start_date' => $params['start_date'],
            'end_date' => $params['end_date'],
            'number_of_entries' => $expenses->count() + $income->count() + $savings->count(),
            'number_income_entries' => $income->count(),
            'number_savings_entries' => $savings->count(),
            'number_expenses_entries' => $expenses->count(),
            'total_expenses_amount' => $expenses->sum('amount'),
            'total_income_amount' => $income->sum('amount'),
            'total_saved_amount' => $savings->sum('amount'),
            'daily_income' => $dailyIncome,
            'daily_expenses' => $dailyExpenses,
            'daily_savings' => $dailySavings
        ];
    }

    private function groupByDay($collection)
    {
        return $collection
            ->groupBy(function ($item) {
                return \Carbon\Carbon::parse($item->created_at)->format('d-m-Y');
            })
            ->map(function ($dayGroup) {
                return $dayGroup->sum('amount');
            })
            ->sortKeys() // Sort by date
            ->toArray();
    }
}
