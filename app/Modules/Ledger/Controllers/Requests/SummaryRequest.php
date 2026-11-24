<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Rules\WithinOneYear;
use App\Modules\Ledger\Support\SummaryPeriod;
use Illuminate\Foundation\Http\FormRequest;

final class SummaryRequest extends FormRequest
{
    private const MONTH_FORMAT = 'date_format:Y-m';

    private const FIRST_MONTH = 'after_or_equal:2000-01';

    /**
     * @return array<string, list<string|WithinOneYear>>
     */
    public function rules(): array
    {
        return [
            'month' => ['bail', 'nullable', self::MONTH_FORMAT, self::FIRST_MONTH, 'prohibits:from,to'],
            'from' => ['bail', 'nullable', 'required_with:to', MoneyRules::DATE_FORMAT, 'after_or_equal:'.MoneyRules::EARLIEST_DATE],
            'to' => ['bail', 'nullable', 'required_with:from', MoneyRules::DATE_FORMAT, 'after_or_equal:from', new WithinOneYear('from')],
        ];
    }

    public function period(Book $book): SummaryPeriod
    {
        $from = MoneyRules::optionalString($this->validated('from'));
        $to = MoneyRules::optionalString($this->validated('to'));

        if ($from !== null && $to !== null) {
            return SummaryPeriod::range($from, $to);
        }

        $month = MoneyRules::optionalString($this->validated('month'));

        return $month === null ? SummaryPeriod::thisMonth($book) : SummaryPeriod::month($month);
    }
}
