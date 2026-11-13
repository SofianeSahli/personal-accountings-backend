<?php

declare(strict_types=1);

namespace App\Shared\Money;

final readonly class Money
{
    private function __construct(
        private int $minorUnits,
        private Currency $currency,
    ) {}

    public static function of(int $minorUnits, Currency|string $currency): self
    {
        return new self($minorUnits, $currency instanceof Currency ? $currency : Currency::of($currency));
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->withResult($this->minorUnits + $other->minorUnits);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->withResult($this->minorUnits - $other->minorUnits);
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits <=> $other->minorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw MoneyException::currencyMismatch($this->currency, $other->currency);
        }
    }

    private function withResult(int|float $result): self
    {
        if (! is_int($result)) {
            throw MoneyException::overflow();
        }

        return new self($result, $this->currency);
    }
}
