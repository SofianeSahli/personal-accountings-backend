<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\CategoryType;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    LedgerScenario::categories();
});

it('REQ-1.1 records an expense in a personal book (token mode)', function (): void {
    $user = User::factory()->create(['name' => 'Sofiane']);
    $book = LedgerScenario::personalBook($user);

    $response = postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry([
        'amount' => 12_500,
        'date' => '2026-11-20',
        'comment' => 'Courses de la semaine',
    ]), HouseholdScenario::as($user))->assertCreated();

    $entry = Transaction::query()->sole();
    $response->assertExactJson(['data' => [
        'id' => $entry->id,
        'amount' => 12_500,
        'currency' => 'TND',
        'date' => '2026-11-20',
        'type' => 'expense',
        'category' => LedgerScenario::SUPERMARKET,
        'other_name' => null,
        'comment' => 'Courses de la semaine',
        'recurring_rule_id' => null,
        'created_by' => ['id' => $user->id, 'name' => 'Sofiane'],
        'updated_by' => ['id' => $user->id, 'name' => 'Sofiane'],
        'created_at' => $entry->created_at->toIso8601ZuluString(),
        'updated_at' => $entry->updated_at->toIso8601ZuluString(),
    ]]);
    expect($entry->book_id)->toBe($book->id)
        ->and($entry->amount)->toBe(12_500)
        ->and($entry->created_by)->toBe($user->id);
});

it('REQ-1.1 and E2 let a household member record in the household book (cookie mode)', function (): void {
    [, $member, $book] = LedgerScenario::householdBook();

    WebBrowser::signedInAs($member)->postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry())
        ->assertCreated()
        ->assertJsonPath('data.created_by', ['id' => $member->id, 'name' => 'Amira']);
});

it('REQ-1.1 and E3 take the type from the category', function (string $code, string $type): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(['category' => $code]), HouseholdScenario::as($user))
        ->assertCreated()
        ->assertJsonPath('data.type', $type)
        ->assertJsonPath('data.amount', 12_500);
})->with([
    'expense' => [LedgerScenario::SUPERMARKET, 'expense'],
    'income' => [LedgerScenario::SALARY, 'income'],
    'saving' => [LedgerScenario::EMERGENCY_FUND, 'saving'],
]);

it('REQ-0.5 sends the book currency with the integer amount', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user, currency: 'EUR');

    postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(['amount' => 999_999_999_999]), HouseholdScenario::as($user))
        ->assertCreated()
        ->assertJsonPath('data.amount', 999_999_999_999)
        ->assertJsonPath('data.currency', 'EUR');
});

it('REQ-0.4 and REQ-1.2 validate every field', function (array $body, string $field, string $code): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    Category::factory()->under(LedgerScenario::category('food.groceries'), 'old', 9)->retired()->create();

    postJson(LedgerScenario::transactionsUrl($book), $body, HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [$field => [$code]]]]);

    expect(Transaction::query()->count())->toBe(0);
})->with([
    'no amount' => [LedgerScenario::entry(['amount' => null]), 'amount', 'amount.required'],
    'amount as text' => [LedgerScenario::entry(['amount' => '125']), 'amount', 'amount.integer'],
    'decimal amount' => [LedgerScenario::entry(['amount' => 12.5]), 'amount', 'amount.integer'],
    'zero amount' => [LedgerScenario::entry(['amount' => 0]), 'amount', 'amount.min'],
    'negative amount' => [LedgerScenario::entry(['amount' => -5]), 'amount', 'amount.min'],
    'huge amount' => [LedgerScenario::entry(['amount' => 1_000_000_000_000]), 'amount', 'amount.max'],
    'no date' => [LedgerScenario::entry(['date' => null]), 'date', 'date.required'],
    'bad date' => [LedgerScenario::entry(['date' => '20/11/2026']), 'date', 'date.date_format'],
    'impossible date' => [LedgerScenario::entry(['date' => '2026-02-30']), 'date', 'date.date_format'],
    'too old' => [LedgerScenario::entry(['date' => '1999-12-31']), 'date', 'date.after_or_equal'],
    'no category' => [LedgerScenario::entry(['category' => null]), 'category', 'category.required'],
    'unknown category' => [LedgerScenario::entry(['category' => 'food.nope.nope']), 'category', 'category.not_found'],
    'group' => [LedgerScenario::entry(['category' => 'food.groceries']), 'category', 'category.not_leaf'],
    'top' => [LedgerScenario::entry(['category' => 'food']), 'category', 'category.not_leaf'],
    'retired leaf' => [LedgerScenario::entry(['category' => 'food.groceries.old']), 'category', 'category.retired'],
    'other without name' => [LedgerScenario::entry(['category' => LedgerScenario::GROCERIES_OTHER]), 'other_name', 'other_name.required'],
    'other with blank name' => [LedgerScenario::entry(['category' => LedgerScenario::GROCERIES_OTHER, 'other_name' => '   ']), 'other_name', 'other_name.required'],
    'name on a normal leaf' => [LedgerScenario::entry(['other_name' => 'Épicerie']), 'other_name', 'other_name.prohibited'],
    'long other name' => [LedgerScenario::entry(['category' => LedgerScenario::GROCERIES_OTHER, 'other_name' => str_repeat('é', 61)]), 'other_name', 'other_name.max'],
    'long comment' => [LedgerScenario::entry(['comment' => str_repeat('x', 201)]), 'comment', 'comment.max'],
    'bad id' => [LedgerScenario::entry(['id' => 'abc']), 'id', 'id.uuid'],
]);

it('REQ-1.2 accepts dates from 2000-01-01 to one year ahead in the book timezone', function (): void {
    travelTo(CarbonImmutable::parse('2026-11-22 23:30:00', 'UTC'));
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user, 'Africa/Tunis');

    foreach (['2000-01-01', '2027-11-23'] as $date) {
        postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(['date' => $date]), HouseholdScenario::as($user))->assertCreated();
    }

    postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(['date' => '2027-11-24']), HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertJsonPath('error.fields.date', ['date.before_or_equal']);
});

it('E5 and E6 trim the other name and the comment, and store an empty comment as null', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry([
        'category' => LedgerScenario::GROCERIES_OTHER,
        'other_name' => '  Épicerie fine  ',
        'comment' => '   ',
    ]), HouseholdScenario::as($user))
        ->assertCreated()
        ->assertJsonPath('data.other_name', 'Épicerie fine')
        ->assertJsonPath('data.comment', null);
});

it('REQ-0.4 never takes the book, the author or the rule from the body', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $other = LedgerScenario::personalBook(User::factory()->create());

    postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry([
        'book_id' => $other->id,
        'created_by' => $other->owner_id,
        'recurring_rule_id' => (string) Str::uuid(),
    ]), HouseholdScenario::as($user))->assertCreated();

    expect(Transaction::query()->sole()->only(['book_id', 'created_by', 'recurring_rule_id']))
        ->toBe(['book_id' => $book->id, 'created_by' => $user->id, 'recurring_rule_id' => null]);
});

it('REQ-1.3 stores the client id and answers the stored entry when it is sent again', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $id = (string) Str::uuid();

    postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(['id' => $id, 'amount' => 100]), HouseholdScenario::as($user))
        ->assertCreated()
        ->assertJsonPath('data.id', $id);

    postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(['id' => strtoupper($id), 'amount' => 999]), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.amount', 100);

    expect(Transaction::query()->count())->toBe(1);
});

it('REQ-1.3 refuses a client id used in another book or by a deleted entry', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $elsewhere = Transaction::factory()->create();
    $deleted = Transaction::factory()->inBook($book)->create();
    $deleted->delete();

    foreach ([$elsewhere->id, $deleted->id] as $id) {
        postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(['id' => $id]), HouseholdScenario::as($user))
            ->assertStatus(409)
            ->assertExactJson(['error' => ['code' => 'transaction.id_conflict']]);
    }
});

it('REQ-1.4 generates an id when none is sent', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    $id = postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(), HouseholdScenario::as($user))
        ->assertCreated()
        ->json('data.id');

    expect(Str::isUuid($id))->toBeTrue();
});

it('R3 refuses a retired leaf even when its type would fit', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $retired = LedgerScenario::leaf('income.work.bonus', CategoryType::Income);
    $retired->update(['retired_at' => now()]);

    postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(['category' => 'income.work.bonus']), HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertJsonPath('error.fields.category', ['category.retired']);
});
