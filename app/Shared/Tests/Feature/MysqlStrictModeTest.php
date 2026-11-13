<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    DB::statement('CREATE TEMPORARY TABLE strict_probe (label VARCHAR(3) NULL, happened_on DATE NULL, tiny TINYINT NULL)');
});

afterEach(function (): void {
    DB::statement('DROP TEMPORARY TABLE IF EXISTS strict_probe');
});

it('REQ-2.4 runs tests on the MySQL database expenses_test', function (): void {
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::connection()->getDatabaseName())->toBe('expenses_test');
});

it('REQ-2.6 runs the session with every strict sql_mode flag', function (): void {
    $sqlMode = (string) DB::scalar('SELECT @@SESSION.sql_mode');

    expect(explode(',', $sqlMode))->toContain(
        'STRICT_TRANS_TABLES',
        'ONLY_FULL_GROUP_BY',
        'NO_ZERO_DATE',
        'NO_ZERO_IN_DATE',
        'ERROR_FOR_DIVISION_BY_ZERO',
    );
});

it('REQ-2.8 uses utf8mb4 and a UTC session time zone', function (): void {
    expect(DB::scalar('SELECT @@SESSION.time_zone'))->toBe('+00:00')
        ->and(DB::scalar('SELECT @@SESSION.character_set_connection'))->toBe('utf8mb4')
        ->and(DB::scalar('SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()'))->toBe('utf8mb4');
});

it('REQ-2.7 rejects a string longer than its column', function (): void {
    expect(fn () => DB::insert('INSERT INTO strict_probe (label) VALUES (?)', ['abcd']))
        ->toThrow(QueryException::class);
});

it('REQ-2.7 rejects an invalid date', function (): void {
    expect(fn () => DB::insert('INSERT INTO strict_probe (happened_on) VALUES (?)', ['2026-02-30']))
        ->toThrow(QueryException::class);
});

it('REQ-2.7 rejects an out-of-range integer', function (): void {
    expect(fn () => DB::insert('INSERT INTO strict_probe (tiny) VALUES (?)', [128]))
        ->toThrow(QueryException::class);
});
