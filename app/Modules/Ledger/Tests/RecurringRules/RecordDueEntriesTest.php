<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Actions\RecordDueEntries;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Frequency;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    LedgerScenario::categories();
});

function travelToUtc(string $moment): void
{
    travelTo(CarbonImmutable::parse($moment, 'UTC'));
}

it('REQ-4.5 records a due entry with the rule values and its author', function (): void {
    travelToUtc('2026-12-01 08:00:00');
    $author = User::factory()->create();
    $rule = RecurringRule::factory()->by($author)
        ->in(LedgerScenario::category(LedgerScenario::GROCERIES_OTHER), 'Nounou')
        ->startingOn('2026-12-01')
        ->create(['amount' => 450_000, 'comment' => 'Décembre']);

    artisan('ledger:record-due-entries')
        ->expectsOutput('Recurring rules: 1 checked, 1 entries recorded, 0 failed.')
        ->assertSuccessful();

    $entry = Transaction::query()->sole();
    expect($entry->only(['book_id', 'category_id', 'recurring_rule_id', 'rule_due_on', 'amount', 'date', 'other_name', 'comment', 'created_by', 'updated_by']))
        ->toBe([
            'book_id' => $rule->book_id,
            'category_id' => $rule->category_id,
            'recurring_rule_id' => $rule->id,
            'rule_due_on' => '2026-12-01',
            'amount' => 450_000,
            'date' => '2026-12-01',
            'other_name' => 'Nounou',
            'comment' => 'Décembre',
            'created_by' => $author->id,
            'updated_by' => $author->id,
        ])
        ->and($rule->fresh()?->next_due_on)->toBe('2027-01-01');
});

it('E11 never records an entry before its date', function (): void {
    travelToUtc('2026-11-30 08:00:00');
    RecurringRule::factory()->startingOn('2026-12-01')->create();

    artisan('ledger:record-due-entries')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(0);
});

it('E11 catches up every missed due date', function (): void {
    travelToUtc('2026-12-20 08:00:00');
    RecurringRule::factory()->startingOn('2026-11-29', Frequency::Weekly)->create();

    artisan('ledger:record-due-entries')->assertSuccessful();

    expect(Transaction::query()->orderBy('date')->pluck('date')->all())->toBe(['2026-11-29', '2026-12-06', '2026-12-13', '2026-12-20']);
});

it('R10 reaches each book\'s today after its own midnight', function (): void {
    travelToUtc('2026-11-30 23:30:00');
    $tunis = RecurringRule::factory()->inBook(Book::factory()->create(['timezone' => 'Africa/Tunis']))->startingOn('2026-12-01')->create();
    $newYork = RecurringRule::factory()->inBook(Book::factory()->create(['timezone' => 'America/New_York']))->startingOn('2026-12-01')->create();

    app(RecordDueEntries::class)->handle();

    expect(Transaction::query()->pluck('recurring_rule_id')->all())->toBe([$tunis->id]);

    travelToUtc('2026-12-01 05:30:00');
    app(RecordDueEntries::class)->handle();

    expect(Transaction::query()->where('recurring_rule_id', $newYork->id)->count())->toBe(1);
});

it('R10 and REQ-4.6 change nothing when run twice, and never recreate a deleted occurrence', function (): void {
    travelToUtc('2026-12-01 08:00:00');
    $rule = RecurringRule::factory()->startingOn('2026-12-01')->create();

    app(RecordDueEntries::class)->handle();
    Transaction::query()->sole()->delete();
    $rule->update(['next_due_on' => '2026-12-01']);
    $report = app(RecordDueEntries::class)->handle();

    expect(Transaction::withTrashed()->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(0)
        ->and($report->entries)->toBe(0)
        ->and($rule->fresh()?->next_due_on)->toBe('2027-01-01');
});

it('REQ-4.6 refuses a second entry for the same due date at the database level', function (): void {
    $rule = RecurringRule::factory()->create();
    Transaction::factory()->create(['recurring_rule_id' => $rule->id, 'rule_due_on' => '2026-12-01']);

    expect(fn () => Transaction::factory()->create(['recurring_rule_id' => $rule->id, 'rule_due_on' => '2026-12-01']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('R9 makes nothing after ends_on and marks the rule finished', function (): void {
    travelToUtc('2027-03-15 08:00:00');
    $rule = RecurringRule::factory()->startingOn('2026-12-01')->create(['ends_on' => '2027-01-31']);

    app(RecordDueEntries::class)->handle();

    expect(Transaction::query()->orderBy('date')->pluck('date')->all())->toBe(['2026-12-01', '2027-01-01'])
        ->and($rule->fresh()?->next_due_on)->toBeNull();
});

it('REQ-4.10 and REQ-4.12 skip deleted rules and rules of deleted books', function (): void {
    travelToUtc('2026-12-01 08:00:00');
    RecurringRule::factory()->startingOn('2026-12-01')->create()->delete();
    $book = Book::factory()->create();
    RecurringRule::factory()->inBook($book)->startingOn('2026-12-01')->create();
    $book->delete();

    $report = app(RecordDueEntries::class)->handle();

    expect(Transaction::query()->count())->toBe(0)
        ->and($report->rules)->toBe(0);
});

it('REQ-4.7 logs a failing rule with ids only and goes on with the others', function (): void {
    travelToUtc('2026-12-01 08:00:00');
    $broken = Book::factory()->create(['timezone' => 'Nowhere/Secret']);
    $failing = RecurringRule::factory()->inBook($broken)->startingOn('2026-12-01')->create();
    $working = RecurringRule::factory()->startingOn('2026-12-01')->create();
    $errors = [];
    Event::listen(MessageLogged::class, function (MessageLogged $logged) use (&$errors): void {
        if ($logged->level === 'error') {
            $errors[] = [$logged->message, $logged->context];
        }
    });

    artisan('ledger:record-due-entries')
        ->expectsOutput('Recurring rules: 2 checked, 1 entries recorded, 1 failed.')
        ->assertFailed();

    expect(Transaction::query()->pluck('recurring_rule_id')->all())->toBe([$working->id])
        ->and($failing->fresh()?->next_due_on)->toBe('2026-12-01')
        ->and($errors)->toHaveCount(1)
        ->and($errors[0][0])->toBe('ledger.recurring_rule_failed')
        ->and(array_keys($errors[0][1]))->toBe(['rule_id', 'book_id', 'exception'])
        ->and($errors[0][1]['rule_id'])->toBe($failing->id)
        ->and($errors[0][1]['book_id'])->toBe($broken->id)
        ->and(json_encode($errors))->not->toContain('Secret');
});

it('REQ-4.5 schedules the job every hour without overlapping', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'ledger:record-due-entries'));

    expect($event)->toBeInstanceOf(ScheduledEvent::class)
        ->and($event?->expression)->toBe('0 * * * *')
        ->and($event?->withoutOverlapping)->toBeTrue();
});
