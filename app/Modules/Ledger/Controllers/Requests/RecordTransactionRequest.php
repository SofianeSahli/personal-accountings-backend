<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Support\NewTransaction;
use Illuminate\Foundation\Http\FormRequest;

final class RecordTransactionRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid'],
            'amount' => ['bail', 'required', ...MoneyRules::amount()],
            'date' => ['required', ...MoneyRules::entryDate()],
            'category' => ['required', 'string'],
            'other_name' => MoneyRules::otherName(),
            'comment' => MoneyRules::comment(),
        ];
    }

    public function newTransaction(): NewTransaction
    {
        $id = MoneyRules::optionalString($this->validated('id'));

        return new NewTransaction(
            id: $id === null ? null : strtolower($id),
            amount: (int) $this->validated('amount'),
            date: (string) $this->validated('date'),
            categoryCode: (string) $this->validated('category'),
            otherName: MoneyRules::optionalString($this->validated('other_name')),
            comment: MoneyRules::optionalString($this->validated('comment')),
        );
    }
}
