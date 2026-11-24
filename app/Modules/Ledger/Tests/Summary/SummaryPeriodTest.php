<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

use function Pest\Laravel\getJson;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    LedgerScenario::categories();
});

it('REQ-1.1 summarizes the current month in the book\'s timezone by default', function (): void {
    $user = User::factory()->create();
    $tunis = LedgerScenario::personalBook($user, 'Africa/Tunis');
    $utc = LedgerScenario::personalBook($user, 'UTC');
    travelTo('2026-11-30 23:30:00');

    getJson(LedgerScenario::summaryUrl($tunis), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.from', '2026-12-01')
        ->assertJsonPath('data.to', '2026-12-31');

    getJson(LedgerScenario::summaryUrl($utc), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.from', '2026-11-01')
        ->assertJsonPath('data.to', '2026-11-30');
});

it('REQ-1.2 summarizes one month from its first to its last day', function (string $month, string $from, string $to): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    getJson(LedgerScenario::summaryUrl($book, 'month='.$month), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.from', $from)
        ->assertJsonPath('data.to', $to);
})->with([
    'november' => ['2026-11', '2026-11-01', '2026-11-30'],
    'february' => ['2026-02', '2026-02-01', '2026-02-28'],
    'leap february' => ['2028-02', '2028-02-01', '2028-02-29'],
    'first month' => ['2000-01', '2000-01-01', '2000-01-31'],
]);

it('REQ-1.3 summarizes a custom range of up to one year, both days included', function (string $from, string $to): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, $from);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 2_000, $to);

    getJson(LedgerScenario::summaryUrl($book, "from={$from}&to={$to}"), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.from', $from)
        ->assertJsonPath('data.to', $to)
        ->assertJsonPath('data.totals.expense', 3_000);
})->with([
    'pay day to pay day' => ['2026-10-25', '2026-11-24'],
    'a whole year' => ['2026-01-01', '2026-12-31'],
    'leap day to the day before' => ['2024-02-29', '2025-02-27'],
]);

it('REQ-1.4 refuses a malformed period with its field code', function (string $query, string $field, string $code): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    getJson(LedgerScenario::summaryUrl($book, $query), HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation.failed')
        ->assertJsonPath("error.fields.{$field}", [$code]);
})->with([
    'month not a month' => ['month=2026-13', 'month', 'month.date_format'],
    'month as a day' => ['month=2026-11-01', 'month', 'month.date_format'],
    'month too early' => ['month=1999-12', 'month', 'month.after_or_equal'],
    'month with a range' => ['month=2026-11&from=2026-11-01&to=2026-11-02', 'month', 'month.prohibits'],
    'to alone' => ['to=2026-11-02', 'from', 'from.required_with'],
    'from alone' => ['from=2026-11-01', 'to', 'to.required_with'],
    'from not a real day' => ['from=2026-02-30&to=2026-03-01', 'from', 'from.date_format'],
    'from too early' => ['from=1999-12-31&to=2000-01-05', 'from', 'from.after_or_equal'],
    'to not a real day' => ['from=2026-11-01&to=2026-11-31', 'to', 'to.date_format'],
    'to before from' => ['from=2026-11-02&to=2026-11-01', 'to', 'to.after_or_equal'],
    'more than a year' => ['from=2026-01-01&to=2027-01-01', 'to', 'to.within_one_year'],
    'leap day plus a year' => ['from=2024-02-29&to=2025-02-28', 'to', 'to.within_one_year'],
]);
