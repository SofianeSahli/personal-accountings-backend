<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Support\BudgetMonth;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Carbon\CarbonImmutable;

use function Pest\Laravel\json;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-1.1 gives a month its key, first and last day, and its previous month', function (string $key, string $first, string $last, string $previous): void {
    $month = BudgetMonth::parse($key);

    expect([$month->key, $month->first, $month->last, $month->previous()->key])->toBe([$key, $first, $last, $previous]);
})->with([
    'november' => ['2026-11', '2026-11-01', '2026-11-30', '2026-10'],
    'january' => ['2027-01', '2027-01-01', '2027-01-31', '2026-12'],
    'leap february' => ['2028-02', '2028-02-01', '2028-02-29', '2028-01'],
    'march after a short february' => ['2026-03', '2026-03-01', '2026-03-31', '2026-02'],
]);

it('REQ-1.1 accepts every month from 2000-01 to the current month plus 12, for every verb', function (string $method, string $month): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $suffix = $method === 'POST' ? '/copy' : '';
    BudgetScenario::plan($book, BudgetMonth::parse($month)->previous()->key, ['food' => 1_000]);

    json($method, BudgetScenario::url($book, $month, $suffix), ['lines' => []], HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.month', $month);
})->with(['GET', 'PUT', 'POST'])->with(['2000-01', '2026-11', '2027-11']);

it('REQ-1.1 refuses a month that is malformed or outside the window, for every verb', function (string $method, string $month, string $code): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $suffix = $method === 'POST' ? '/copy' : '';

    json($method, BudgetScenario::url($book, $month, $suffix), ['lines' => 'not an array', 'replace' => 'yes'], HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['month' => [$code]]]]);
})->with(['GET', 'PUT', 'POST'])->with([
    'month 13' => ['2026-13', 'month.date_format'],
    'month 00' => ['2026-00', 'month.date_format'],
    'one digit' => ['2026-1', 'month.date_format'],
    'a day' => ['2026-11-01', 'month.date_format'],
    'not a date' => ['november', 'month.date_format'],
    'before 2000' => ['1999-12', 'month.after_or_equal'],
    'current month plus 13' => ['2027-12', 'month.before_or_equal'],
]);

it('REQ-1.2 computes the current month in the book\'s timezone', function (): void {
    $user = User::factory()->create();
    $tunis = LedgerScenario::personalBook($user, 'Africa/Tunis');
    $utc = LedgerScenario::personalBook($user, 'UTC');
    travelTo(CarbonImmutable::parse('2026-11-30 23:30:00', 'UTC'));

    expect(BudgetMonth::current($tunis)->key)->toBe('2026-12')
        ->and(BudgetMonth::current($utc)->key)->toBe('2026-11');

    json('GET', BudgetScenario::url($tunis, '2027-12'), [], HouseholdScenario::as($user))->assertOk();
    json('GET', BudgetScenario::url($utc, '2027-12'), [], HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertJsonPath('error.fields.month', ['month.before_or_equal']);
});
