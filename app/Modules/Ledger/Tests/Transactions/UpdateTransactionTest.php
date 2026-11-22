<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

beforeEach(function (): void {
    LedgerScenario::categories();
});

it('REQ-3.1 changes only the fields sent and records the editor (token mode)', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();
    $entry = Transaction::factory()->inBook($book)->by($owner)
        ->in(LedgerScenario::category(LedgerScenario::SUPERMARKET))
        ->create(['amount' => 1_000, 'date' => '2026-11-01', 'comment' => 'Avant']);

    patchJson(LedgerScenario::transactionsUrl($book, '/'.$entry->id), ['amount' => 2_500], HouseholdScenario::as($member))
        ->assertOk()
        ->assertJsonPath('data.amount', 2_500)
        ->assertJsonPath('data.date', '2026-11-01')
        ->assertJsonPath('data.comment', 'Avant')
        ->assertJsonPath('data.created_by.id', $owner->id)
        ->assertJsonPath('data.updated_by', ['id' => $member->id, 'name' => 'Amira']);
});

it('REQ-3.1 changes every editable field (cookie mode)', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $entry = Transaction::factory()->inBook($book)->by($user)->in(LedgerScenario::category(LedgerScenario::SUPERMARKET))->create();

    WebBrowser::signedInAs($user)->patchJson(LedgerScenario::transactionsUrl($book, '/'.$entry->id), [
        'amount' => 7,
        'date' => '2026-01-02',
        'category' => LedgerScenario::SALARY,
        'comment' => 'Prime',
    ])
        ->assertOk()
        ->assertJsonPath('data.amount', 7)
        ->assertJsonPath('data.date', '2026-01-02')
        ->assertJsonPath('data.category', LedgerScenario::SALARY)
        ->assertJsonPath('data.type', 'income')
        ->assertJsonPath('data.comment', 'Prime');
});

it('REQ-3.1 clears the comment when it is sent empty', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $entry = Transaction::factory()->inBook($book)->create(['comment' => 'Note']);

    patchJson(LedgerScenario::transactionsUrl($book, '/'.$entry->id), ['comment' => ''], HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.comment', null);
});

it('REQ-3.1 validates the fields sent', function (array $body, string $field, string $code): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $entry = Transaction::factory()->inBook($book)->in(LedgerScenario::category(LedgerScenario::SUPERMARKET))->create(['amount' => 10]);

    patchJson(LedgerScenario::transactionsUrl($book, '/'.$entry->id), $body, HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [$field => [$code]]]]);

    expect($entry->fresh()?->amount)->toBe(10);
})->with([
    'empty amount' => [['amount' => null], 'amount', 'amount.required'],
    'zero amount' => [['amount' => 0], 'amount', 'amount.min'],
    'empty date' => [['date' => ''], 'date', 'date.required'],
    'bad date' => [['date' => '2026-1-2'], 'date', 'date.date_format'],
    'group category' => [['category' => 'food'], 'category', 'category.not_leaf'],
    'unknown category' => [['category' => 'nope'], 'category', 'category.not_found'],
    'name on a normal leaf' => [['other_name' => 'X'], 'other_name', 'other_name.prohibited'],
    'to an Other leaf without name' => [['category' => LedgerScenario::GROCERIES_OTHER], 'other_name', 'other_name.required'],
    'long comment' => [['comment' => str_repeat('x', 201)], 'comment', 'comment.max'],
]);

it('REQ-3.2 keeps the Other name when the Other leaf does not change, and requires it when cleared', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $entry = Transaction::factory()->inBook($book)->in(LedgerScenario::category(LedgerScenario::GROCERIES_OTHER), 'Épicerie')->create();
    $url = LedgerScenario::transactionsUrl($book, '/'.$entry->id);

    patchJson($url, ['amount' => 5], HouseholdScenario::as($user))->assertOk()->assertJsonPath('data.other_name', 'Épicerie');
    patchJson($url, ['other_name' => 'Marché'], HouseholdScenario::as($user))->assertOk()->assertJsonPath('data.other_name', 'Marché');
    patchJson($url, ['other_name' => null], HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertJsonPath('error.fields.other_name', ['other_name.required']);
});

it('REQ-3.2 clears the Other name when moving to another leaf', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $entry = Transaction::factory()->inBook($book)->in(LedgerScenario::category(LedgerScenario::GROCERIES_OTHER), 'Épicerie')->create();

    patchJson(LedgerScenario::transactionsUrl($book, '/'.$entry->id), ['category' => LedgerScenario::SUPERMARKET], HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.category', LedgerScenario::SUPERMARKET)
        ->assertJsonPath('data.other_name', null);
});

it('REQ-3.3 edits an entry whose leaf was retired without changing its category', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $retired = LedgerScenario::leaf('food.groceries.old');
    $entry = Transaction::factory()->inBook($book)->in($retired)->create();
    $retired->update(['retired_at' => now()]);

    patchJson(LedgerScenario::transactionsUrl($book, '/'.$entry->id), ['amount' => 3, 'category' => 'food.groceries.old'], HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.category', 'food.groceries.old');
});

it('R16 never changes the book, the author or the rule link', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $rule = RecurringRule::factory()->inBook($book)->by($user)->create();
    $entry = Transaction::factory()->inBook($book)->by($user)->create(['recurring_rule_id' => $rule->id, 'rule_due_on' => '2026-11-01']);
    $before = $entry->only(['book_id', 'created_by', 'recurring_rule_id', 'rule_due_on']);

    patchJson(LedgerScenario::transactionsUrl($book, '/'.$entry->id), [
        'book_id' => LedgerScenario::personalBook(User::factory()->create())->id,
        'created_by' => User::factory()->create()->id,
        'recurring_rule_id' => null,
        'date' => '2026-11-03',
    ], HouseholdScenario::as($user))->assertOk()->assertJsonPath('data.recurring_rule_id', $rule->id);

    expect($entry->fresh()?->only(['book_id', 'created_by', 'recurring_rule_id', 'rule_due_on']))->toBe($before);
});

it('REQ-3.4 soft-deletes an entry, which then answers 404', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $entry = Transaction::factory()->inBook($book)->create();
    $url = LedgerScenario::transactionsUrl($book, '/'.$entry->id);

    deleteJson($url, [], HouseholdScenario::as($user))->assertNoContent();

    expect(Transaction::withTrashed()->find($entry->id)?->trashed())->toBeTrue();
    getJson($url, HouseholdScenario::as($user))->assertStatus(404)->assertExactJson(['error' => ['code' => 'transaction.not_found']]);
    deleteJson($url, [], HouseholdScenario::as($user))->assertStatus(404);
    patchJson($url, ['amount' => 1], HouseholdScenario::as($user))->assertStatus(404);
});

it('REQ-3.5 lets any member edit and delete an entry another member recorded (cookie mode)', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();
    $byOwner = Transaction::factory()->inBook($book)->by($owner)->create();
    $browser = WebBrowser::signedInAs($member);

    $browser->patchJson(LedgerScenario::transactionsUrl($book, '/'.$byOwner->id), ['amount' => 42])->assertOk();
    $browser->deleteJson(LedgerScenario::transactionsUrl($book, '/'.$byOwner->id))->assertNoContent();
});

it('REQ-3.6 leaves the rule and its other entries alone when one generated entry changes', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $rule = RecurringRule::factory()->inBook($book)->by($user)->create(['amount' => 500]);
    $first = Transaction::factory()->inBook($book)->create(['recurring_rule_id' => $rule->id, 'rule_due_on' => '2026-10-01', 'amount' => 500]);
    $second = Transaction::factory()->inBook($book)->create(['recurring_rule_id' => $rule->id, 'rule_due_on' => '2026-11-01', 'amount' => 500]);

    patchJson(LedgerScenario::transactionsUrl($book, '/'.$first->id), ['amount' => 900], HouseholdScenario::as($user))->assertOk();
    deleteJson(LedgerScenario::transactionsUrl($book, '/'.$first->id), [], HouseholdScenario::as($user))->assertNoContent();

    expect($rule->fresh()?->amount)->toBe(500)
        ->and($rule->fresh()?->trashed())->toBeFalse()
        ->and($second->fresh()?->amount)->toBe(500);
});
