<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Models\Frequency;
use App\Modules\Ledger\Support\RecurringRuleChanges;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\In;

final class UpdateRecurringRuleRequest extends FormRequest
{
    /**
     * @return array<string, list<string|In>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['bail', 'sometimes', 'required', ...MoneyRules::amount()],
            'category' => ['sometimes', 'required', 'string'],
            'other_name' => MoneyRules::otherName(),
            'comment' => MoneyRules::comment(),
            'frequency' => ['sometimes', 'required', MoneyRules::frequency()],
            'interval' => ['bail', 'sometimes', 'required', ...MoneyRules::interval()],
            'starts_on' => ['sometimes', 'required', ...MoneyRules::startDate()],
            'ends_on' => ['nullable', MoneyRules::DATE_FORMAT],
        ];
    }

    public function changes(): RecurringRuleChanges
    {
        $validated = $this->validated();
        $amount = $validated['amount'] ?? null;
        $interval = $validated['interval'] ?? null;
        $frequency = MoneyRules::optionalString($validated['frequency'] ?? null);

        return new RecurringRuleChanges(
            amount: is_int($amount) ? $amount : null,
            categoryCode: MoneyRules::optionalString($validated['category'] ?? null),
            sendsOtherName: array_key_exists('other_name', $validated),
            otherName: MoneyRules::optionalString($validated['other_name'] ?? null),
            sendsComment: array_key_exists('comment', $validated),
            comment: MoneyRules::optionalString($validated['comment'] ?? null),
            frequency: $frequency === null ? null : Frequency::from($frequency),
            interval: is_int($interval) ? $interval : null,
            startsOn: MoneyRules::optionalString($validated['starts_on'] ?? null),
            sendsEndsOn: array_key_exists('ends_on', $validated),
            endsOn: MoneyRules::optionalString($validated['ends_on'] ?? null),
        );
    }
}
