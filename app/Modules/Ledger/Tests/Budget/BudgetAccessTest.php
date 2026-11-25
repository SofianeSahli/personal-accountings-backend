<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Illuminate\Support\Str;

use function Pest\Laravel\json;
use function Pest\Laravel\travelBack;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

dataset('budget routes', [
    'read' => ['GET', ''],
    'save' => ['PUT', ''],
    'copy' => ['POST', '/copy'],
]);

it('REQ-0.1 answers 401 without a token or a session, in both modes', function (string $method, string $suffix): void {
    travelBack();
    $book = Book::factory()->create();
    $url = BudgetScenario::url($book, '2026-11', $suffix);

    json($method, $url, ['lines' => []])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);

    $browser = WebBrowser::open();
    $response = match ($method) {
        'GET' => $browser->getJson($url),
        'PUT' => $browser->putJson($url, ['lines' => []]),
        default => $browser->postJson($url),
    };
    $response->assertStatus(401)->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
})->with('budget routes');

it('REQ-0.2 answers the same 404 for a book of another household, of another account, deleted, unknown or malformed', function (string $method, string $suffix): void {
    $caller = User::factory()->create();
    HouseholdScenario::ownedBy($caller);
    $otherHousehold = Book::factory()->ownedByHousehold(HouseholdScenario::ownedBy(User::factory()->create())->id)->create();
    $deleted = LedgerScenario::personalBook($caller);
    $deleted->delete();
    $ids = [$otherHousehold->id, Book::factory()->create()->id, $deleted->id, (string) Str::uuid(), 'not-a-uuid'];

    foreach ($ids as $id) {
        json($method, "/api/v1/books/{$id}/budgets/1999-01{$suffix}", ['lines' => 'bad'], HouseholdScenario::as($caller))
            ->assertStatus(404)
            ->assertExactJson(['error' => ['code' => 'book.not_found']]);
    }

    expect(BudgetPlan::query()->count())->toBe(0);
})->with('budget routes');

it('REQ-0.3 lets every live member read, save and copy the household book\'s budget, in both modes', function (): void {
    travelBack();
    [$owner, $member, $book] = LedgerScenario::householdBook();
    BudgetScenario::plan($book, '2026-10', ['food' => 1_000]);

    BudgetScenario::copy($member, $book, '2026-11')->assertOk()->assertJsonPath('data.lines.0.planned', 1_000);
    WebBrowser::signedInAs($owner)->putJson(BudgetScenario::url($book, '2026-11'), ['lines' => [['category' => 'food', 'planned' => 2_000]]])
        ->assertOk()
        ->assertJsonPath('data.lines.0.planned', 2_000);
    WebBrowser::signedInAs($member)->postJson(BudgetScenario::url($book, '2026-12', '/copy'))->assertOk();
    WebBrowser::signedInAs($member)->getJson(BudgetScenario::url($book, '2026-12'))
        ->assertOk()
        ->assertJsonPath('data.lines.0.planned', 2_000);
    expect(BudgetScenario::read($owner, $book, '2026-11')['lines'][0]['planned'])->toBe(2_000);
});

it('REQ-0.4 never shows or changes another book\'s plan', function (): void {
    $user = User::factory()->create();
    $mine = LedgerScenario::personalBook($user);
    $other = LedgerScenario::personalBook($user);
    BudgetScenario::plan($other, '2026-11', ['food' => 9_000], income: 7);
    BudgetScenario::plan($other, '2026-10', ['transport' => 9_000]);

    expect(BudgetScenario::read($user, $mine, '2026-11')['has_plan'])->toBeFalse();
    BudgetScenario::copy($user, $mine, '2026-11')->assertStatus(409)->assertJsonPath('error.code', 'budget.nothing_to_copy');
    BudgetScenario::save($user, $mine, '2026-11', ['lines' => [['category' => 'health', 'planned' => 1]]])->assertOk();

    expect(BudgetScenario::linesOf($other, '2026-11'))->toBe([['category' => 'food', 'planned' => 9_000]])
        ->and(BudgetScenario::read($user, $other, '2026-11')['income']['expected'])->toBe(7);
});

it('REQ-0.2 ends access at once when a member leaves', function (): void {
    [, $member, $book] = LedgerScenario::householdBook();
    BudgetScenario::read($member, $book, '2026-11');

    HouseholdScenario::leave($member);

    BudgetScenario::save($member, $book, '2026-11', ['lines' => []])
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'book.not_found']]);
});
