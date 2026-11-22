<?php

declare(strict_types=1);

use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Frequency;
use App\Modules\Ledger\Support\BookDay;
use App\Modules\Ledger\Support\Schedule;
use Carbon\CarbonImmutable;

use function Pest\Laravel\travelTo;

/**
 * @return list<?string>
 */
function dueDates(Schedule $schedule, int $count): array
{
    $dates = [];

    for ($index = 0; $index < $count; $index++) {
        $dates[] = $schedule->dueDate($index);
    }

    return $dates;
}

it('REQ-4.3 repeats weekly, monthly and yearly from the start date', function (Frequency $frequency, int $interval, array $expected): void {
    expect(dueDates(new Schedule($frequency, $interval, '2026-01-15', null), 3))->toBe($expected);
})->with([
    'weekly' => [Frequency::Weekly, 1, ['2026-01-15', '2026-01-22', '2026-01-29']],
    'every 2 weeks' => [Frequency::Weekly, 2, ['2026-01-15', '2026-01-29', '2026-02-12']],
    'monthly' => [Frequency::Monthly, 1, ['2026-01-15', '2026-02-15', '2026-03-15']],
    'every 3 months' => [Frequency::Monthly, 3, ['2026-01-15', '2026-04-15', '2026-07-15']],
    'yearly' => [Frequency::Yearly, 1, ['2026-01-15', '2027-01-15', '2028-01-15']],
    'every 12 years' => [Frequency::Yearly, 12, ['2026-01-15', '2038-01-15', '2050-01-15']],
]);

it('REQ-4.3 falls on the last day of a shorter month and comes back to the start day', function (): void {
    expect(dueDates(new Schedule(Frequency::Monthly, 1, '2026-01-31', null), 5))
        ->toBe(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31'])
        ->and(dueDates(new Schedule(Frequency::Monthly, 1, '2027-12-31', null), 3))
        ->toBe(['2027-12-31', '2028-01-31', '2028-02-29']);
});

it('REQ-4.3 keeps 29 February on 28 February in common years and 29 in leap years', function (): void {
    expect(dueDates(new Schedule(Frequency::Yearly, 1, '2028-02-29', null), 5))
        ->toBe(['2028-02-29', '2029-02-28', '2030-02-28', '2031-02-28', '2032-02-29']);
});

it('REQ-4.3 makes no due date after ends_on', function (): void {
    $schedule = new Schedule(Frequency::Monthly, 1, '2026-01-10', '2026-03-10');

    expect(dueDates($schedule, 4))->toBe(['2026-01-10', '2026-02-10', '2026-03-10', null])
        ->and($schedule->firstAfter('2026-03-10'))->toBeNull()
        ->and($schedule->firstOnOrAfter('2026-03-11'))->toBeNull();
});

it('REQ-4.3 finds the first due date on or after a day', function (Frequency $frequency, int $interval, string $start, string $day, string $expected): void {
    expect((new Schedule($frequency, $interval, $start, null))->firstOnOrAfter($day))->toBe($expected);
})->with([
    'before the start' => [Frequency::Monthly, 1, '2026-05-10', '2026-01-01', '2026-05-10'],
    'on a due date' => [Frequency::Monthly, 1, '2026-01-10', '2026-04-10', '2026-04-10'],
    'between due dates' => [Frequency::Monthly, 1, '2026-01-10', '2026-04-11', '2026-05-10'],
    'month end' => [Frequency::Monthly, 1, '2026-01-31', '2026-02-27', '2026-02-28'],
    'weekly far away' => [Frequency::Weekly, 1, '2026-01-01', '2030-06-15', '2030-06-20'],
    'every 2 months' => [Frequency::Monthly, 2, '2026-01-10', '2026-02-11', '2026-03-10'],
    'yearly' => [Frequency::Yearly, 1, '2026-06-01', '2027-06-02', '2028-06-01'],
]);

it('REQ-4.3 gives the next due date after a due date', function (): void {
    expect((new Schedule(Frequency::Weekly, 1, '2026-01-01', null))->firstAfter('2026-01-01'))->toBe('2026-01-08');
});

it('REQ-6.5 reads today in the book timezone around midnight', function (string $utc, string $timezone, string $expected): void {
    travelTo(CarbonImmutable::parse($utc, 'UTC'));

    expect(BookDay::today(new Book(['timezone' => $timezone])))->toBe($expected);
})->with([
    'ahead of UTC, already tomorrow' => ['2026-11-22 23:30:00', 'Pacific/Kiritimati', '2026-11-23'],
    'Tunis, already tomorrow' => ['2026-11-22 23:30:00', 'Africa/Tunis', '2026-11-23'],
    'behind UTC, still yesterday' => ['2026-11-23 00:30:00', 'America/New_York', '2026-11-22'],
    'UTC' => ['2026-11-23 00:30:00', 'UTC', '2026-11-23'],
]);

it('REQ-6.5 adds a year without overflowing 29 February', function (): void {
    expect(BookDay::addYears('2028-02-29', 1))->toBe('2029-02-28')
        ->and(BookDay::addYears('2026-11-22', -1))->toBe('2025-11-22');
});
