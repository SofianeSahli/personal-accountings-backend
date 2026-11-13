<?php

declare(strict_types=1);

use App\Shared\Money\Currency;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyException;

it('REQ-8.1 holds an integer amount in minor units and a currency', function (): void {
    $money = Money::of(1500, 'TND');

    expect($money->minorUnits())->toBe(1500)
        ->and($money->currency()->code)->toBe('TND');
});

it('REQ-8.1 accepts a Currency instance', function (): void {
    expect(Money::of(100, Currency::of('EUR'))->currency()->code)->toBe('EUR');
});

it('REQ-8.10 stores one unit of a currency as its exact minor units', function (string $code, int $oneUnit): void {
    $exponent = Currency::of($code)->exponent;

    expect(Money::of(10 ** $exponent, $code)->minorUnits())->toBe($oneUnit);
})->with([
    'TND 1.000' => ['TND', 1000],
    'EUR 1.00' => ['EUR', 100],
    'JPY 1' => ['JPY', 1],
]);

it('REQ-8.3 rejects an unknown currency', function (string $code): void {
    expect(fn () => Money::of(100, $code))->toThrow(MoneyException::class);
})->with(['XYZ', 'EURO']);

it('REQ-8.4 refuses an amount that is not an int', function (mixed $amount): void {
    expect(fn () => Money::of($amount, 'EUR'))->toThrow(TypeError::class);
})->with([
    'float' => 1.5,
    'whole float' => 1.0,
    'numeric string' => '100',
    'decimal string' => '1.00',
]);

it('REQ-8.5 allows zero and negative amounts', function (): void {
    expect(Money::of(0, 'EUR')->minorUnits())->toBe(0)
        ->and(Money::of(-250, 'EUR')->minorUnits())->toBe(-250);
});

it('REQ-8.6 adds two amounts of the same currency exactly', function (): void {
    $sum = Money::of(1250, 'TND')->plus(Money::of(750, 'TND'));

    expect($sum->minorUnits())->toBe(2000)
        ->and($sum->currency()->code)->toBe('TND');
});

it('REQ-8.6 subtracts two amounts of the same currency exactly', function (): void {
    expect(Money::of(100, 'EUR')->minus(Money::of(250, 'EUR'))->minorUnits())->toBe(-150);
});

it('REQ-8.1 never changes the original value', function (): void {
    $original = Money::of(100, 'EUR');
    $original->plus(Money::of(50, 'EUR'));

    expect($original->minorUnits())->toBe(100);
});

it('REQ-8.7 refuses to mix currencies', function (Closure $operation): void {
    expect(fn () => $operation(Money::of(100, 'EUR'), Money::of(100, 'TND')))->toThrow(MoneyException::class);
})->with([
    'plus' => fn (Money $a, Money $b): Money => $a->plus($b),
    'minus' => fn (Money $a, Money $b): Money => $a->minus($b),
    'equals' => fn (Money $a, Money $b): bool => $a->equals($b),
    'compareTo' => fn (Money $a, Money $b): int => $a->compareTo($b),
    'greaterThan' => fn (Money $a, Money $b): bool => $a->greaterThan($b),
    'lessThan' => fn (Money $a, Money $b): bool => $a->lessThan($b),
]);

it('REQ-8.8 refuses an addition beyond the 64-bit range', function (): void {
    expect(fn () => Money::of(PHP_INT_MAX, 'EUR')->plus(Money::of(1, 'EUR')))->toThrow(MoneyException::class);
});

it('REQ-8.8 refuses a subtraction beyond the 64-bit range', function (): void {
    expect(fn () => Money::of(PHP_INT_MIN, 'EUR')->minus(Money::of(1, 'EUR')))->toThrow(MoneyException::class);
});

it('REQ-8.8 keeps the exact limits of the 64-bit range', function (): void {
    expect(Money::of(PHP_INT_MAX - 1, 'EUR')->plus(Money::of(1, 'EUR'))->minorUnits())->toBe(PHP_INT_MAX)
        ->and(Money::of(PHP_INT_MIN + 1, 'EUR')->minus(Money::of(1, 'EUR'))->minorUnits())->toBe(PHP_INT_MIN);
});

it('REQ-8.9 compares amounts of the same currency', function (): void {
    $ten = Money::of(10, 'EUR');
    $twenty = Money::of(20, 'EUR');

    expect($ten->equals(Money::of(10, 'eur')))->toBeTrue()
        ->and($ten->equals($twenty))->toBeFalse()
        ->and($ten->compareTo($twenty))->toBe(-1)
        ->and($twenty->compareTo($ten))->toBe(1)
        ->and($ten->compareTo(Money::of(10, 'EUR')))->toBe(0)
        ->and($twenty->greaterThan($ten))->toBeTrue()
        ->and($ten->greaterThan($ten))->toBeFalse()
        ->and($ten->lessThan($twenty))->toBeTrue()
        ->and($ten->lessThan($ten))->toBeFalse();
});

it('REQ-8.9 reports the sign of an amount', function (int $minorUnits, bool $zero, bool $negative, bool $positive): void {
    $money = Money::of($minorUnits, 'EUR');

    expect($money->isZero())->toBe($zero)
        ->and($money->isNegative())->toBe($negative)
        ->and($money->isPositive())->toBe($positive);
})->with([
    'zero' => [0, true, false, false],
    'negative' => [-1, false, true, false],
    'positive' => [1, false, false, true],
]);
