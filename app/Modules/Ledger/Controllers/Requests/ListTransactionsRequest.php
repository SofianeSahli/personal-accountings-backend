<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Models\CategoryType;
use App\Modules\Ledger\Support\TransactionFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class ListTransactionsRequest extends FormRequest
{
    /**
     * @return array<string, list<string|In>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', MoneyRules::DATE_FORMAT],
            'to' => ['bail', 'nullable', MoneyRules::DATE_FORMAT, 'after_or_equal:from'],
            'category' => ['nullable', 'string'],
            'type' => ['nullable', Rule::in(array_column(CategoryType::cases(), 'value'))],
            'author' => ['nullable', 'uuid'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function filters(): TransactionFilters
    {
        $type = MoneyRules::optionalString($this->validated('type'));
        $author = MoneyRules::optionalString($this->validated('author'));

        return new TransactionFilters(
            from: MoneyRules::optionalString($this->validated('from')),
            to: MoneyRules::optionalString($this->validated('to')),
            categoryCode: MoneyRules::optionalString($this->validated('category')),
            type: $type === null ? null : CategoryType::from($type),
            authorId: $author === null ? null : strtolower($author),
        );
    }
}
