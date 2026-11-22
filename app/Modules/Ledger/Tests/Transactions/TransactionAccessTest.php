<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\LedgerWriteLimit;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;
use function Pest\Laravel\json;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    LedgerScenario::categories();
});

dataset('money routes', [
    'list entries' => ['GET', 'transactions'],
    'record an entry' => ['POST', 'transactions'],
    'show an entry' => ['GET', 'transactions/{entry}'],
    'edit an entry' => ['PATCH', 'transactions/{entry}'],
    'delete an entry' => ['DELETE', 'transactions/{entry}'],
    'list rules' => ['GET', 'recurring-rules'],
    'create a rule' => ['POST', 'recurring-rules'],
    'show a rule' => ['GET', 'recurring-rules/{rule}'],
    'edit a rule' => ['PATCH', 'recurring-rules/{rule}'],
    'delete a rule' => ['DELETE', 'recurring-rules/{rule}'],
    'suggestions' => ['GET', 'other-names?category=food.groceries.other'],
]);

function moneyUrl(Book $book, string $path): string
{
    $entry = Transaction::factory()->inBook($book)->create();
    $rule = RecurringRule::factory()->inBook($book)->create();

    return "/api/v1/books/{$book->id}/".str_replace(['{entry}', '{rule}'], [$entry->id, $rule->id], $path);
}

it('REQ-0.1 answers 401 without a session or a token', function (string $method, string $path): void {
    json($method, moneyUrl(Book::factory()->create(), $path), LedgerScenario::entry())
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
})->with('money routes');

it('REQ-0.1 answers 401 to a browser that is not signed in (cookie mode)', function (): void {
    $book = Book::factory()->create();

    WebBrowser::open()->getJson(LedgerScenario::transactionsUrl($book))->assertStatus(401);
    WebBrowser::open()->postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry())->assertStatus(401);
});

it('REQ-0.1 answers the same 404 for a book of another household, of another account, deleted or unknown', function (string $method, string $path): void {
    $caller = User::factory()->create();
    HouseholdScenario::ownedBy($caller);
    $otherHousehold = Book::factory()->ownedByHousehold(HouseholdScenario::ownedBy(User::factory()->create())->id)->create();
    $otherAccount = Book::factory()->create();
    $deleted = LedgerScenario::personalBook($caller);
    $deleted->delete();

    foreach ([$otherHousehold, $otherAccount, $deleted] as $book) {
        json($method, moneyUrl($book, $path), LedgerScenario::entry(), HouseholdScenario::as($caller))
            ->assertStatus(404)
            ->assertExactJson(['error' => ['code' => 'book.not_found']]);
    }

    $unknown = '/api/v1/books/'.Str::uuid().'/'.str_replace(['{entry}', '{rule}'], (string) Str::uuid(), $path);
    json($method, $unknown, LedgerScenario::entry(), HouseholdScenario::as($caller))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'book.not_found']]);

    expect(Transaction::query()->where('created_by', $caller->id)->count())->toBe(0);
})->with('money routes');

it('REQ-0.2 and E2 let a household member use every route of the household book', function (string $method, string $path): void {
    [, $member, $book] = LedgerScenario::householdBook();

    $body = $method === 'GET' ? [] : LedgerScenario::entry(LedgerScenario::rule());
    $status = json($method, moneyUrl($book, $path), $body, HouseholdScenario::as($member))->status();

    expect($status)->toBeIn([200, 201, 204]);
})->with('money routes');

it('REQ-0.2 ends access at once when a member leaves', function (): void {
    [, $member, $book] = LedgerScenario::householdBook();
    getJson(LedgerScenario::transactionsUrl($book), HouseholdScenario::as($member))->assertOk();

    HouseholdScenario::leave($member);

    getJson(LedgerScenario::transactionsUrl($book), HouseholdScenario::as($member))->assertStatus(404);
    postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(), HouseholdScenario::as($member))->assertStatus(404);
});

it('REQ-0.3 answers the same 404 for an entry of another book, deleted, unknown or malformed', function (string $method): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $mine = LedgerScenario::personalBook($user);
    $deleted = Transaction::factory()->inBook($book)->create();
    $deleted->delete();
    $ids = [
        Transaction::factory()->inBook($mine)->create()->id,
        Transaction::factory()->create()->id,
        $deleted->id,
        (string) Str::uuid(),
        'not-a-uuid',
    ];

    foreach ($ids as $id) {
        json($method, LedgerScenario::transactionsUrl($book, '/'.$id), ['amount' => 0], HouseholdScenario::as($user))
            ->assertStatus(404)
            ->assertExactJson(['error' => ['code' => 'transaction.not_found']]);
    }
})->with(['GET', 'PATCH', 'DELETE']);

it('REQ-0.6 limits writes to 60 a minute per account, entries and rules together', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $headers = HouseholdScenario::as($user);

    for ($i = 1; $i < LedgerWriteLimit::PER_MINUTE; $i++) {
        postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(), $headers)->assertCreated();
    }

    postJson(LedgerScenario::rulesUrl($book), LedgerScenario::rule(), $headers)->assertCreated();
    postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(), $headers)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
    getJson(LedgerScenario::transactionsUrl($book), $headers)->assertOk();

    $other = User::factory()->create();
    postJson(LedgerScenario::transactionsUrl(LedgerScenario::personalBook($other)), LedgerScenario::entry(), HouseholdScenario::as($other))
        ->assertCreated();
});
