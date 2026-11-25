<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Support\NewBudgetPlan;
use App\Modules\Ledger\Support\PlanLineInput;
use Illuminate\Foundation\Http\FormRequest;

final class SaveBudgetRequest extends FormRequest
{
    use ValidatesBudgetMonth;

    public const MAX_LINES = 100;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'expected_income' => ['nullable', ...$this->planAmount()],
            'planned_saving' => ['nullable', ...$this->planAmount()],
            'lines' => ['present', 'array', 'max:'.self::MAX_LINES],
            'lines.*.category' => ['bail', 'required', 'string', 'distinct'],
            'lines.*.planned' => ['bail', 'required', ...$this->planAmount()],
        ];
    }

    public function newBudgetPlan(): NewBudgetPlan
    {
        $lines = [];

        foreach ((array) $this->validated('lines') as $line) {
            $line = (array) $line;
            $lines[] = new PlanLineInput((string) $line['category'], (int) $line['planned']);
        }

        return new NewBudgetPlan(
            month: $this->month(),
            expectedIncome: $this->optionalAmount('expected_income'),
            plannedSaving: $this->optionalAmount('planned_saving'),
            lines: $lines,
        );
    }

    /**
     * @return list<string>
     */
    private function planAmount(): array
    {
        return ['integer:strict', 'min:0', 'max:'.MoneyRules::MAX_AMOUNT];
    }

    private function optionalAmount(string $field): ?int
    {
        $value = $this->validated($field);

        return is_int($value) ? $value : null;
    }
}
