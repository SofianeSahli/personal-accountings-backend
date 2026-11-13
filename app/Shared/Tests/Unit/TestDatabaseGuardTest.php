<?php

declare(strict_types=1);

use Tests\Support\TestDatabaseGuard;

it('REQ-2.5 refuses to run tests on a database whose name does not end in _test', function (string $database): void {
    expect(fn () => TestDatabaseGuard::ensure($database))->toThrow(RuntimeException::class);
})->with(['expenses_db', 'expenses_test_copy', '']);

it('REQ-2.5 accepts a database whose name ends in _test', function (): void {
    expect(fn () => TestDatabaseGuard::ensure('expenses_test'))->not->toThrow(RuntimeException::class);
});
