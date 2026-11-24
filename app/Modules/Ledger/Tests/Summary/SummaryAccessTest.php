<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    LedgerScenario::categories();
});

it('REQ-0.1 answers 401 without a token or a session', function (): void {
    $book = Book::factory()->create();

    getJson(LedgerScenario::summaryUrl($book))
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);

    WebBrowser::open()->getJson(LedgerScenario::summaryUrl($book))
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('REQ-0.2 answers the same 404 for a book of another household, of another account, deleted, unknown or malformed', function (): void {
    $caller = User::factory()->create();
    HouseholdScenario::ownedBy($caller);
    $otherHousehold = Book::factory()->ownedByHousehold(HouseholdScenario::ownedBy(User::factory()->create())->id)->create();
    $deleted = LedgerScenario::personalBook($caller);
    $deleted->delete();
    $ids = [$otherHousehold->id, Book::factory()->create()->id, $deleted->id, (string) Str::uuid(), 'not-a-uuid'];

    foreach ($ids as $id) {
        getJson("/api/v1/books/{$id}/summary", HouseholdScenario::as($caller))
            ->assertStatus(404)
            ->assertExactJson(['error' => ['code' => 'book.not_found']]);
    }
});

it('REQ-0.3 lets every live member read the household book\'s summary, in both modes', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-01', $owner);

    getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), HouseholdScenario::as($owner))
        ->assertOk()
        ->assertJsonPath('data.totals.expense', 1_000);
    WebBrowser::signedInAs($member)->getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'))
        ->assertOk()
        ->assertJsonPath('data.totals.expense', 1_000);
});

it('REQ-0.2 ends access at once when a member leaves', function (): void {
    [, $member, $book] = LedgerScenario::householdBook();
    getJson(LedgerScenario::summaryUrl($book), HouseholdScenario::as($member))->assertOk();

    HouseholdScenario::leave($member);

    getJson(LedgerScenario::summaryUrl($book), HouseholdScenario::as($member))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'book.not_found']]);
});
