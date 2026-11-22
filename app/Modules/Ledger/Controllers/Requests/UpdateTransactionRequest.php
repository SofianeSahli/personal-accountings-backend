<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Support\TransactionChanges;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTransactionRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['bail', 'sometimes', 'required', ...MoneyRules::amount()],
            'date' => ['sometimes', 'required', ...MoneyRules::entryDate()],
            'category' => ['sometimes', 'required', 'string'],
            'other_name' => MoneyRules::otherName(),
            'comment' => MoneyRules::comment(),
        ];
    }

    public function changes(): TransactionChanges
    {
        $validated = $this->validated();
        $amount = $validated['amount'] ?? null;

        return new TransactionChanges(
            amount: is_int($amount) ? $amount : null,
            date: MoneyRules::optionalString($validated['date'] ?? null),
            categoryCode: MoneyRules::optionalString($validated['category'] ?? null),
            sendsOtherName: array_key_exists('other_name', $validated),
            otherName: MoneyRules::optionalString($validated['other_name'] ?? null),
            sendsComment: array_key_exists('comment', $validated),
            comment: MoneyRules::optionalString($validated['comment'] ?? null),
        );
    }
}
