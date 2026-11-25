<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Resources;

use App\Modules\Ledger\Support\Budget;
use App\Modules\Ledger\Support\BudgetLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BudgetResource extends JsonResource
{
    public function __construct(private readonly Budget $budget)
    {
        parent::__construct($budget);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'month' => $this->budget->month->key,
            'from' => $this->budget->month->first,
            'to' => $this->budget->month->last,
            'currency' => $this->budget->currency,
            'has_plan' => $this->budget->hasPlan,
            'days_left' => $this->budget->daysLeft,
            'income' => ['expected' => $this->budget->expectedIncome, 'actual' => $this->budget->actualIncome],
            'saving' => ['planned' => $this->budget->plannedSaving, 'actual' => $this->budget->actualSaving],
            'expenses' => [
                'planned' => $this->budget->plannedExpenses,
                'actual' => $this->budget->actualExpenses,
                'off_plan' => $this->budget->offPlan,
                'remaining' => $this->budget->remaining,
            ],
            'lines' => array_map(fn (BudgetLine $line): array => [
                'category' => $line->category,
                'planned' => $line->planned,
                'actual' => $line->actual,
                'percent' => $line->percent,
                'status' => $line->status->value,
                'retired' => $line->retired,
            ], $this->budget->lines),
        ];
    }
}
