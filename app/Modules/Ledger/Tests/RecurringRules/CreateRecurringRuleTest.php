<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Actions\CreateRecurringRule;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Carbon\CarbonImmutable;

use function Pest\Laravel\postJson;
use function Pest\Laravel\travelBack;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    LedgerScenario::categories();
    travelTo(CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC'));
});

it('REQ-4.1 creates a rule that starts later and makes no entry yet (token mode)', function (): void {
    $user = User::factory()->create(['name' => 'Sofiane']);
    $book = LedgerScenario::personalBook($user);

    $response = postJson(LedgerScenario::rulesUrl($book), [
        'amount' => 850_000,
        'category' => LedgerScenario::SUPERMARKET,
        'comment' => 'Loyer',
        'frequency' => 'monthly',
        'starts_on' => '2026-12-01',
    ], HouseholdScenario::as($user))->assertCreated();

    $rule = RecurringRule::query()->sole();
    $response->assertExactJson(['data' => [
        'id' => $rule->id,
        'amount' => 850_000,
        'currency' => 'TND',
        'type' => 'expense',
        'category' => LedgerScenario::SUPERMARKET,
        'other_name' => null,
        'comment' => 'Loyer',
        'frequency' => 'monthly',
        'interval' => 1,
        'starts_on' => '2026-12-01',
        'ends_on' => null,
        'next_date' => '2026-12-01',
        'created_by' => ['id' => $user->id, 'name' => 'Sofiane'],
        'updated_by' => ['id' => $user->id, 'name' => 'Sofiane'],
        'created_at' => $rule->created_at->toIso8601ZuluString(),
        'updated_at' => $rule->updated_at->toIso8601ZuluString(),
    ]]);
    expect(Transaction::query()->count())->toBe(0);
});

it('REQ-4.4 and E12 backfill every due date from a past start up to today, in one go (cookie mode)', function (): void {
    [, $member, $book] = LedgerScenario::householdBook();
    travelBack();
    $browser = WebBrowser::signedInAs($member);
    travelTo(CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC'));

    $browser->postJson(LedgerScenario::rulesUrl($book), LedgerScenario::rule([
        'amount' => 300_000,
        'category' => LedgerScenario::GROCERIES_OTHER,
        'other_name' => 'Femme de ménage',
        'starts_on' => '2026-08-22',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.next_date', '2026-12-22');

    $entries = Transaction::query()->orderBy('date')->get();
    $rule = RecurringRule::query()->sole();
    expect($entries->pluck('date')->all())->toBe(['2026-08-22', '2026-09-22', '2026-10-22', '2026-11-22'])
        ->and($entries->pluck('rule_due_on')->all())->toBe(['2026-08-22', '2026-09-22', '2026-10-22', '2026-11-22'])
        ->and($entries->pluck('amount')->unique()->all())->toBe([300_000])
        ->and($entries->pluck('other_name')->unique()->all())->toBe(['Femme de ménage'])
        ->and($entries->pluck('created_by')->unique()->all())->toBe([$member->id])
        ->and($entries->pluck('recurring_rule_id')->unique()->all())->toBe([$rule->id]);
});

it('REQ-4.4 backfills at most 53 weekly entries for a start one year ago', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    postJson(LedgerScenario::rulesUrl($book), LedgerScenario::rule(['frequency' => 'weekly', 'starts_on' => '2025-11-22']), HouseholdScenario::as($user))
        ->assertCreated()
        ->assertJsonPath('data.next_date', '2026-11-28');

    expect(Transaction::query()->count())->toBe(53);
});

it('REQ-4.4 uses the book timezone for today', function (): void {
    travelTo(CarbonImmutable::parse('2026-11-22 23:30:00', 'UTC'));
    $user = User::factory()->create();
    $tunis = LedgerScenario::personalBook($user, 'Africa/Tunis');
    $newYork = LedgerScenario::personalBook($user, 'America/New_York');

    foreach ([$tunis, $newYork] as $book) {
        postJson(LedgerScenario::rulesUrl($book), LedgerScenario::rule(['starts_on' => '2026-11-23']), HouseholdScenario::as($user))->assertCreated();
    }

    expect(Transaction::query()->where('book_id', $tunis->id)->count())->toBe(1)
        ->and(Transaction::query()->where('book_id', $newYork->id)->count())->toBe(0);
});

it('REQ-4.2 validates the rule fields', function (array $body, string $field, string $code): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    postJson(LedgerScenario::rulesUrl($book), $body, HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [$field => [$code]]]]);

    expect(RecurringRule::query()->count())->toBe(0);
})->with([
    'no amount' => [LedgerScenario::rule(['amount' => null]), 'amount', 'amount.required'],
    'zero amount' => [LedgerScenario::rule(['amount' => 0]), 'amount', 'amount.min'],
    'no frequency' => [LedgerScenario::rule(['frequency' => null]), 'frequency', 'frequency.required'],
    'daily' => [LedgerScenario::rule(['frequency' => 'daily']), 'frequency', 'frequency.in'],
    'interval 0' => [LedgerScenario::rule(['interval' => 0]), 'interval', 'interval.min'],
    'interval 13' => [LedgerScenario::rule(['interval' => 13]), 'interval', 'interval.max'],
    'interval as text' => [LedgerScenario::rule(['interval' => '2']), 'interval', 'interval.integer'],
    'no start' => [LedgerScenario::rule(['starts_on' => null]), 'starts_on', 'starts_on.required'],
    'start too early' => [LedgerScenario::rule(['starts_on' => '2025-11-21']), 'starts_on', 'starts_on.after_or_equal'],
    'start too late' => [LedgerScenario::rule(['starts_on' => '2027-11-23']), 'starts_on', 'starts_on.before_or_equal'],
    'end before start' => [LedgerScenario::rule(['starts_on' => '2026-12-10', 'ends_on' => '2026-12-09']), 'ends_on', 'ends_on.after_or_equal'],
    'bad end' => [LedgerScenario::rule(['ends_on' => '31/12/2026']), 'ends_on', 'ends_on.date_format'],
    'no category' => [LedgerScenario::rule(['category' => null]), 'category', 'category.required'],
    'group category' => [LedgerScenario::rule(['category' => 'food.groceries']), 'category', 'category.not_leaf'],
    'other without name' => [LedgerScenario::rule(['category' => LedgerScenario::GROCERIES_OTHER]), 'other_name', 'other_name.required'],
    'name on a normal leaf' => [LedgerScenario::rule(['other_name' => 'X']), 'other_name', 'other_name.prohibited'],
    'long comment' => [LedgerScenario::rule(['comment' => str_repeat('x', 201)]), 'comment', 'comment.max'],
]);

it('REQ-4.2 accepts an interval and an end date', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    postJson(LedgerScenario::rulesUrl($book), LedgerScenario::rule([
        'frequency' => 'yearly',
        'interval' => 2,
        'starts_on' => '2026-12-01',
        'ends_on' => '2030-12-01',
    ]), HouseholdScenario::as($user))
        ->assertCreated()
        ->assertJsonPath('data.interval', 2)
        ->assertJsonPath('data.ends_on', '2030-12-01');
});

it('REQ-4.11 refuses a 101st live rule in a book', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    RecurringRule::factory()->count(CreateRecurringRule::RULES_PER_BOOK)->inBook($book)->create();
    RecurringRule::factory()->inBook(LedgerScenario::personalBook($user))->create();

    postJson(LedgerScenario::rulesUrl($book), LedgerScenario::rule(), HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'recurring_rule.limit_reached']]);

    RecurringRule::query()->where('book_id', $book->id)->firstOrFail()->delete();

    postJson(LedgerScenario::rulesUrl($book), LedgerScenario::rule(), HouseholdScenario::as($user))->assertCreated();
});
