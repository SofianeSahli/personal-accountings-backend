<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Book;
use Illuminate\Support\Carbon;

use function Pest\Laravel\getJson;

it('REQ-3.1 answers an empty list to a user with no book', function (): void {
    getJson('/api/v1/books', HouseholdScenario::as(User::factory()->create()))
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.next_cursor', null);
});

it('REQ-3.1 lists personal books and the household book, oldest first, and no others (token mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    Carbon::setTestNow('2026-11-20 10:00:00');
    $personal = Book::factory()->ownedByAccount($owner)->create(['name' => 'Perso', 'currency' => 'EUR']);
    Carbon::setTestNow('2026-11-21 10:00:00');
    $shared = Book::factory()->ownedByHousehold($household->id)->create(['name' => 'Foyer']);
    Carbon::setTestNow();
    Book::factory()->create();
    Book::factory()->ownedByHousehold(HouseholdScenario::ownedBy(User::factory()->create())->id)->create();
    Book::factory()->ownedByAccount($owner)->create()->delete();

    getJson('/api/v1/books', HouseholdScenario::as($owner))
        ->assertOk()
        ->assertJsonPath('data', [
            [
                'id' => $personal->id,
                'name' => 'Perso',
                'currency' => ['code' => 'EUR', 'exponent' => 2],
                'timezone' => 'Africa/Tunis',
                'owner' => ['type' => 'account', 'id' => $owner->id],
                'can_manage' => true,
            ],
            [
                'id' => $shared->id,
                'name' => 'Foyer',
                'currency' => ['code' => 'TND', 'exponent' => 3],
                'timezone' => 'Africa/Tunis',
                'owner' => ['type' => 'household', 'id' => $household->id],
                'can_manage' => true,
            ],
        ]);
});

it('REQ-3.2 shows a member the household book they cannot manage (cookie mode)', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);
    $shared = Book::factory()->ownedByHousehold($household->id)->create();

    WebBrowser::signedInAs($member)->getJson('/api/v1/books')
        ->assertOk()
        ->assertJsonPath('data.0.id', $shared->id)
        ->assertJsonPath('data.0.can_manage', false)
        ->assertJsonCount(1, 'data');
});

it('REQ-4.2 drops the household book once the membership is gone', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);
    Book::factory()->ownedByHousehold($household->id)->create();

    HouseholdScenario::leave($member);

    getJson('/api/v1/books', HouseholdScenario::as($member))->assertOk()->assertJsonPath('data', []);
});

it('REQ-3.1 pages the list with a cursor', function (): void {
    $user = User::factory()->create();
    Book::factory()->count(26)->ownedByAccount($user)->create();
    $headers = HouseholdScenario::as($user);

    $first = getJson('/api/v1/books', $headers)->assertOk();
    $second = getJson('/api/v1/books?cursor='.$first->json('meta.next_cursor'), $headers)->assertOk();

    expect($first->json('data'))->toHaveCount(25)
        ->and($second->json('data'))->toHaveCount(1)
        ->and($second->json('meta.next_cursor'))->toBeNull();
});

it('REQ-8.1 refuses an anonymous caller', function (): void {
    getJson('/api/v1/books')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});
