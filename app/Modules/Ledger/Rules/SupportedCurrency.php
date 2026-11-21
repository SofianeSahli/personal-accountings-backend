<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Rules;

use App\Shared\Money\Currency;
use App\Shared\Money\MoneyException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SupportedCurrency implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->isKnown($value)) {
            $fail('currency.supported_currency');
        }
    }

    private function isKnown(string $code): bool
    {
        try {
            Currency::of($code);

            return true;
        } catch (MoneyException) {
            return false;
        }
    }
}
