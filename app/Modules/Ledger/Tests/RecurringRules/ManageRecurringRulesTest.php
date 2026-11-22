<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Frequency;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\json;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelBack;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    LedgerScenario::categories();
    travelTo(CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC'));
});

/**
 * @return array{0: RecurringRule, 1: Book}
 */
function ruleStartingOn(string $date, User $user, string $frequency = 'monthly'): array
{
    $book = LedgerScenario::personalBook($user);
    $id = postJson(LedgerScenario::rulesUrl($book), LedgerScenario::rule(['starts_on' => $date, 'frequency' => $frequency, 'amount' => 1_000]), HouseholdScenario::as($user))
        ->assertCreated()
        ->json('data.id');

    return [RecurringRule::query()->findOrFail($id), $book];
}

it('REQ-4.8 lists live rules by next date, finished rules last', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $later = RecurringRule::factory()->inBook($book)->startingOn('2027-01-05')->create();
    $finished = RecurringRule::factory()->inBook($book)->startingOn('2026-01-05')->create(['ends_on' => '2026-02-05', 'next_due_on' => null]);
    $sooner = RecurringRule::factory()->inBook($book)->startingOn('2026-12-01')->create();
    RecurringRule::factory()->inBook($book)->startingOn('2026-12-02')->create()->delete();
    RecurringRule::factory()->startingOn('2026-11-30')->create();

    getJson(LedgerScenario::rulesUrl($book), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$sooner->id, $later->id, $finished->id])
        ->assertJsonPath('data.2.next_date', null)
        ->assertJsonPath('meta.per_page', 25);
});

it('REQ-4.8 shows one rule (cookie mode)', function (): void {
    travelBack();
    [, $member, $book] = LedgerScenario::householdBook();
    $rule = RecurringRule::factory()->inBook($book)->startingOn('2026-12-01', Frequency::Weekly, 2)->create(['amount' => 77]);

    WebBrowser::signedInAs($member)->getJson(LedgerScenario::rulesUrl($book, '/'.$rule->id))
        ->assertOk()
        ->assertJsonPath('data.id', $rule->id)
        ->assertJsonPath('data.amount', 77)
        ->assertJsonPath('data.frequency', 'weekly')
        ->assertJsonPath('data.interval', 2);
});

it('REQ-0.3 answers the same 404 for a rule of another book, deleted, unknown or malformed', function (string $method): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $deleted = RecurringRule::factory()->inBook($book)->create();
    $deleted->delete();

    foreach ([RecurringRule::factory()->create()->id, $deleted->id, (string) Str::uuid(), 'nope'] as $id) {
        json($method, LedgerScenario::rulesUrl($book, '/'.$id), ['amount' => 0], HouseholdScenario::as($user))
            ->assertStatus(404)
            ->assertExactJson(['error' => ['code' => 'recurring_rule.not_found']]);
    }
})->with(['GET', 'PATCH', 'DELETE']);

it('REQ-4.9 and E13 change future entries only', function (): void {
    $user = User::factory()->create();
    [$rule, $book] = ruleStartingOn('2026-10-01', $user);

    patchJson(LedgerScenario::rulesUrl($book, '/'.$rule->id), ['amount' => 2_000, 'comment' => 'Nouveau'], HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.amount', 2_000)
        ->assertJsonPath('data.next_date', '2026-12-01');

    travelTo(CarbonImmutable::parse('2026-12-01 10:00:00', 'UTC'));
    artisan('ledger:record-due-entries')->assertSuccessful();

    expect(Transaction::query()->orderBy('date')->pluck('amount', 'date')->all())
        ->toBe(['2026-10-01' => 1_000, '2026-11-01' => 1_000, '2026-12-01' => 2_000]);
});

it('REQ-4.9 and K7 record what was due under the old values before an edit', function (): void {
    $user = User::factory()->create();
    [$rule, $book] = ruleStartingOn('2026-11-01', $user);
    travelTo(CarbonImmutable::parse('2026-12-01 10:00:00', 'UTC'));

    patchJson(LedgerScenario::rulesUrl($book, '/'.$rule->id), ['amount' => 5_000], HouseholdScenario::as($user))->assertOk();

    expect(Transaction::query()->orderBy('date')->pluck('amount', 'date')->all())
        ->toBe(['2026-11-01' => 1_000, '2026-12-01' => 1_000]);
});

it('R8 applies a new schedule from today and never creates past entries', function (): void {
    $user = User::factory()->create();
    [$rule, $book] = ruleStartingOn('2026-11-01', $user);

    patchJson(LedgerScenario::rulesUrl($book, '/'.$rule->id), ['frequency' => 'weekly', 'starts_on' => '2026-11-08'], HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.frequency', 'weekly')
        ->assertJsonPath('data.next_date', '2026-11-29');

    expect(Transaction::query()->orderBy('date')->pluck('date')->all())->toBe(['2026-11-01', '2026-11-22']);
});

it('R8 creates today\'s entry when the new schedule has one', function (): void {
    $user = User::factory()->create();
    [$rule, $book] = ruleStartingOn('2026-11-01', $user);

    patchJson(LedgerScenario::rulesUrl($book, '/'.$rule->id), ['starts_on' => '2026-11-22'], HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.next_date', '2026-12-22');

    expect(Transaction::query()->orderBy('date')->pluck('date')->all())->toBe(['2026-11-01', '2026-11-22']);
});

it('R9 stops a rule at its new end date and restarts it when the end moves', function (): void {
    $user = User::factory()->create();
    [$rule, $book] = ruleStartingOn('2026-11-01', $user);
    $url = LedgerScenario::rulesUrl($book, '/'.$rule->id);

    patchJson($url, ['ends_on' => '2026-11-30'], HouseholdScenario::as($user))->assertOk()->assertJsonPath('data.next_date', null);
    patchJson($url, ['ends_on' => null], HouseholdScenario::as($user))->assertOk()->assertJsonPath('data.next_date', '2026-12-01');
});

it('REQ-4.9 validates the changes', function (array $body, string $field, string $code): void {
    $user = User::factory()->create();
    [$rule, $book] = ruleStartingOn('2026-11-10', $user);

    patchJson(LedgerScenario::rulesUrl($book, '/'.$rule->id), $body, HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [$field => [$code]]]]);

    expect($rule->fresh()?->amount)->toBe(1_000);
})->with([
    'empty amount' => [['amount' => null], 'amount', 'amount.required'],
    'empty frequency' => [['frequency' => null], 'frequency', 'frequency.required'],
    'bad frequency' => [['frequency' => 'daily'], 'frequency', 'frequency.in'],
    'empty interval' => [['interval' => null], 'interval', 'interval.required'],
    'start too late' => [['starts_on' => '2027-11-23'], 'starts_on', 'starts_on.before_or_equal'],
    'end before the stored start' => [['ends_on' => '2026-11-09'], 'ends_on', 'ends_on.after_or_equal'],
    'to an Other leaf without name' => [['category' => LedgerScenario::GROCERIES_OTHER], 'other_name', 'other_name.required'],
    'unknown category' => [['category' => 'nope'], 'category', 'category.not_found'],
]);

it('REQ-4.10 soft-deletes a rule, keeps its entries and makes no more', function (): void {
    $user = User::factory()->create();
    [$rule, $book] = ruleStartingOn('2026-11-01', $user);

    deleteJson(LedgerScenario::rulesUrl($book, '/'.$rule->id), [], HouseholdScenario::as($user))->assertNoContent();

    travelTo(CarbonImmutable::parse('2027-03-01 10:00:00', 'UTC'));
    artisan('ledger:record-due-entries')->assertSuccessful();

    expect(RecurringRule::withTrashed()->find($rule->id)?->trashed())->toBeTrue()
        ->and(Transaction::query()->pluck('recurring_rule_id')->all())->toBe([$rule->id]);
    getJson(LedgerScenario::rulesUrl($book, '/'.$rule->id), HouseholdScenario::as($user))->assertStatus(404);
    getJson(LedgerScenario::rulesUrl($book), HouseholdScenario::as($user))->assertJsonPath('data', []);
});

it('E2 lets any member edit and delete a rule another member created', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();
    $rule = RecurringRule::factory()->inBook($book)->by($owner)->create();

    patchJson(LedgerScenario::rulesUrl($book, '/'.$rule->id), ['amount' => 9], HouseholdScenario::as($member))
        ->assertOk()
        ->assertJsonPath('data.updated_by.id', $member->id);
    deleteJson(LedgerScenario::rulesUrl($book, '/'.$rule->id), [], HouseholdScenario::as($member))->assertNoContent();
});
