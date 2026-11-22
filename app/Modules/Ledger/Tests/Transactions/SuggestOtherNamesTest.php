<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    LedgerScenario::categories();
});

function typeOther(Book $book, string $name, string $at, ?Category $leaf = null): void
{
    Transaction::factory()->inBook($book)
        ->in($leaf ?? LedgerScenario::category(LedgerScenario::GROCERIES_OTHER), $name)
        ->create(['updated_at' => $at, 'created_at' => $at]);
}

function suggestionsUrl(Book $book, string $code = LedgerScenario::GROCERIES_OTHER): string
{
    return "/api/v1/books/{$book->id}/other-names?category={$code}";
}

it('REQ-5.1 suggests the names typed for that Other leaf, most used first, then most recent', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    typeOther($book, 'Marché', '2026-11-01 10:00:00');
    typeOther($book, 'Marché', '2026-11-02 10:00:00');
    typeOther($book, 'Épicerie', '2026-11-03 10:00:00');
    typeOther($book, 'Boucherie', '2026-11-04 10:00:00');
    RecurringRule::factory()->inBook($book)->in(LedgerScenario::category(LedgerScenario::GROCERIES_OTHER), 'Épicerie')
        ->create(['updated_at' => '2026-10-01 10:00:00']);

    getJson(suggestionsUrl($book), HouseholdScenario::as($user))
        ->assertOk()
        ->assertExactJson(['data' => ['Épicerie', 'Marché', 'Boucherie']]);
});

it('REQ-5.1 reads only this book, this leaf and live entries (cookie mode)', function (): void {
    [, $member, $book] = LedgerScenario::householdBook();
    $otherLeaf = LedgerScenario::leaf('food.eating_out.other');
    typeOther($book, 'Kept', '2026-11-01 10:00:00');
    typeOther(Book::factory()->create(), 'Other book', '2026-11-02 10:00:00');
    typeOther($book, 'Other leaf', '2026-11-03 10:00:00', $otherLeaf);
    Transaction::factory()->inBook($book)->in(LedgerScenario::category(LedgerScenario::GROCERIES_OTHER), 'Deleted')->create()->delete();
    RecurringRule::factory()->inBook($book)->in(LedgerScenario::category(LedgerScenario::GROCERIES_OTHER), 'Deleted rule')->create()->delete();

    WebBrowser::signedInAs($member)->getJson(suggestionsUrl($book))
        ->assertOk()
        ->assertExactJson(['data' => ['Kept']]);
});

it('REQ-5.2 counts names differing only in case as one, shown as most recently typed, and keeps accents apart', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    typeOther($book, 'café', '2026-11-01 10:00:00');
    typeOther($book, 'CAFÉ', '2026-11-02 10:00:00');
    typeOther($book, 'Café', '2026-11-03 10:00:00');
    typeOther($book, 'cafe', '2026-11-04 10:00:00');

    getJson(suggestionsUrl($book), HouseholdScenario::as($user))
        ->assertOk()
        ->assertExactJson(['data' => ['Café', 'cafe']]);
});

it('REQ-5.1 returns at most 10 names', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    foreach (range(1, 12) as $day) {
        typeOther($book, "Nom {$day}", sprintf('2026-11-%02d 10:00:00', $day));
    }

    $names = getJson(suggestionsUrl($book), HouseholdScenario::as($user))->assertOk()->collect('data');

    expect($names->all())->toBe(['Nom 12', 'Nom 11', 'Nom 10', 'Nom 9', 'Nom 8', 'Nom 7', 'Nom 6', 'Nom 5', 'Nom 4', 'Nom 3']);
});

it('REQ-5.3 refuses a missing, unknown or non-Other category', function (string $query, string $code): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    getJson("/api/v1/books/{$book->id}/other-names{$query}", HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['category' => [$code]]]]);
})->with([
    'missing' => ['', 'category.required'],
    'unknown' => ['?category=food.nope.other', 'category.not_found'],
    'normal leaf' => ['?category='.LedgerScenario::SUPERMARKET, 'category.not_other'],
    'group' => ['?category=food.groceries', 'category.not_other'],
]);
