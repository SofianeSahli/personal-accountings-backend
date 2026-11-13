<?php

declare(strict_types=1);

namespace App\Shared\Money;

use ResourceBundle;

final class Currency
{
    private const CODE_PATTERN = '/^[A-Z]{3}$/';

    private const ICU_CURRENCY_BUNDLE = 'ICUDATA-curr';

    private const DEFAULT_META = 'DEFAULT';

    /** @var array<string, self> */
    private static array $resolved = [];

    private function __construct(
        public readonly string $code,
        public readonly int $exponent,
    ) {}

    public static function of(string $code): self
    {
        $code = strtoupper($code);

        return self::$resolved[$code] ??= self::resolve($code);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    private static function resolve(string $code): self
    {
        if (preg_match(self::CODE_PATTERN, $code) !== 1 || ! self::icuKnows($code)) {
            throw MoneyException::unknownCurrency($code);
        }

        return new self($code, self::icuExponent($code));
    }

    private static function icuKnows(string $code): bool
    {
        $currencies = ResourceBundle::create('en', self::ICU_CURRENCY_BUNDLE)?->get('Currencies');

        return $currencies instanceof ResourceBundle && $currencies->get($code) !== null;
    }

    private static function icuExponent(string $code): int
    {
        $meta = ResourceBundle::create('supplementalData', self::ICU_CURRENCY_BUNDLE, false)?->get('CurrencyMeta');

        if (! $meta instanceof ResourceBundle) {
            throw MoneyException::unknownCurrency($code);
        }

        $digits = ($meta->get($code) ?? $meta->get(self::DEFAULT_META))[0] ?? null;

        if (! is_int($digits)) {
            throw MoneyException::unknownCurrency($code);
        }

        return $digits;
    }
}
