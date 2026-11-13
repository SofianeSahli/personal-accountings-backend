<?php

declare(strict_types=1);

namespace App\Shared\Money;

use DomainException;

final class MoneyException extends DomainException
{
    public static function unknownCurrency(string $code): self
    {
        return new self("Unknown currency code [{$code}].");
    }

    public static function currencyMismatch(Currency $expected, Currency $actual): self
    {
        return new self("Cannot combine {$expected->code} with {$actual->code}.");
    }

    public static function overflow(): self
    {
        return new self('The result is outside the 64-bit integer range.');
    }
}
