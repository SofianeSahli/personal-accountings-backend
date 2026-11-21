<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Rules\SupportedCurrency;
use App\Modules\Ledger\Support\NewBook;
use App\Shared\Money\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class CreateBookRequest extends FormRequest
{
    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'name' => BookRules::name(),
            'currency' => ['bail', 'nullable', 'string', new SupportedCurrency],
            'timezone' => ['nullable', ...BookRules::timezone()],
            'household_id' => ['nullable', 'string'],
        ];
    }

    public function newBook(): NewBook
    {
        $currency = $this->validated('currency');
        $timezone = $this->validated('timezone');
        $householdId = $this->validated('household_id');

        return new NewBook(
            name: (string) $this->validated('name'),
            currency: Currency::of(is_string($currency) ? $currency : BookRules::DEFAULT_CURRENCY),
            timezone: is_string($timezone) ? $timezone : BookRules::DEFAULT_TIMEZONE,
            householdId: is_string($householdId) ? $householdId : null,
        );
    }
}
