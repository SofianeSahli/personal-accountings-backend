<?php

declare(strict_types=1);

use App\Shared\Money\Currency;
use App\Shared\Money\MoneyException;

it('REQ-8.2 reads the exponent of a currency from ICU', function (string $code, int $exponent): void {
    expect(Currency::of($code)->exponent)->toBe($exponent);
})->with([
    'Tunisian dinar' => ['TND', 3],
    'euro' => ['EUR', 2],
    'yen' => ['JPY', 0],
    'US dollar' => ['USD', 2],
]);

it('REQ-8.3 uppercases the currency code', function (): void {
    expect(Currency::of('tnd')->code)->toBe('TND');
});

it('REQ-8.3 rejects a code that is not three letters or that ICU does not know', function (string $code): void {
    expect(fn () => Currency::of($code))->toThrow(MoneyException::class);
})->with(['XYZ', 'EURO', 'EU', '', 'E1R', ' EUR']);

it('REQ-8.3 treats two currencies with the same code as equal', function (): void {
    expect(Currency::of('eur')->equals(Currency::of('EUR')))->toBeTrue()
        ->and(Currency::of('EUR')->equals(Currency::of('TND')))->toBeFalse();
});
