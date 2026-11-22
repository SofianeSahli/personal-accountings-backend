<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Models\Frequency;
use App\Modules\Ledger\Support\NewRecurringRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\In;

final class CreateRecurringRuleRequest extends FormRequest
{
    public const DEFAULT_INTERVAL = 1;

    /**
     * @return array<string, list<string|In>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['bail', 'required', ...MoneyRules::amount()],
            'category' => ['required', 'string'],
            'other_name' => MoneyRules::otherName(),
            'comment' => MoneyRules::comment(),
            'frequency' => ['required', MoneyRules::frequency()],
            'interval' => ['bail', 'nullable', ...MoneyRules::interval()],
            'starts_on' => ['required', ...MoneyRules::startDate()],
            'ends_on' => ['bail', 'nullable', MoneyRules::DATE_FORMAT, 'after_or_equal:starts_on'],
        ];
    }

    public function newRule(): NewRecurringRule
    {
        $interval = $this->validated('interval');

        return new NewRecurringRule(
            amount: (int) $this->validated('amount'),
            categoryCode: (string) $this->validated('category'),
            otherName: MoneyRules::optionalString($this->validated('other_name')),
            comment: MoneyRules::optionalString($this->validated('comment')),
            frequency: Frequency::from((string) $this->validated('frequency')),
            interval: is_int($interval) ? $interval : self::DEFAULT_INTERVAL,
            startsOn: (string) $this->validated('starts_on'),
            endsOn: MoneyRules::optionalString($this->validated('ends_on')),
        );
    }
}
