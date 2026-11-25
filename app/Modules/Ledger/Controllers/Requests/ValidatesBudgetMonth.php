<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Support\BudgetMonth;
use App\Modules\Ledger\Support\CurrentBook;
use Illuminate\Support\Facades\Validator;

trait ValidatesBudgetMonth
{
    private const MONTH = 'month';

    private const FIRST_MONTH = '2000-01';

    private const MONTHS_AHEAD = 12;

    public function month(): BudgetMonth
    {
        return BudgetMonth::parse($this->monthInPath());
    }

    protected function prepareForValidation(): void
    {
        Validator::make([self::MONTH => $this->monthInPath()], [self::MONTH => [
            'bail',
            'date_format:'.BudgetMonth::FORMAT,
            'after_or_equal:'.self::FIRST_MONTH,
            'before_or_equal:'.BudgetMonth::current(app(CurrentBook::class)->book)->plusMonths(self::MONTHS_AHEAD)->key,
        ]])->validate();
    }

    private function monthInPath(): string
    {
        $month = $this->route(self::MONTH);

        return is_string($month) ? $month : '';
    }
}
