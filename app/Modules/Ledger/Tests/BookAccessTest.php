<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Book;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;
use function Pest\Laravel\json;

it('REQ-4.1 shows a personal book to its owner (token mode)', function (): void {
    $user = User::factory()->create();
    $book = Book::factory()->ownedByAccount($user)->create(['name' => 'Perso']);

    getJson("/api/v1/books/{$book->id}", HouseholdScenario::as($user))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $book->id,
            'name' => 'Perso',
            'currency' => ['code' => 'TND', 'exponent' => 3],
            'timezone' => 'Africa/Tunis',
            'owner' => ['type' => 'account', 'id' => $user->id],
            'can_manage' => true,
        ]]);
});

it('REQ-4.1 and REQ-8.7 show the household book to every member (cookie mode)', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);
    $book = Book::factory()->ownedByHousehold($household->id)->create();

    WebBrowser::signedInAs($member)->getJson("/api/v1/books/{$book->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $book->id)
        ->assertJsonPath('data.can_manage', false);
});

it('REQ-8.3 answers the same 404 for an unknown, malformed, foreign or deleted book', function (string $method): void {
    $caller = User::factory()->create();
    HouseholdScenario::ownedBy($caller);
    $foreignPersonal = Book::factory()->create();
    $foreignHousehold = Book::factory()->ownedByHousehold(HouseholdScenario::ownedBy(User::factory()->create())->id)->create();
    $deleted = Book::factory()->ownedByAccount($caller)->create();
    $deleted->delete();

    $answers = [];
    foreach ([(string) Str::uuid(), 'not-a-uuid', $foreignPersonal->id, $foreignHousehold->id, $deleted->id] as $id) {
        $response = json($method, '/api/v1/books/'.$id, ['currency' => 'EUR'], HouseholdScenario::as($caller));
        $response->assertStatus(404)->assertExactJson(['error' => ['code' => 'book.not_found']]);
        $headers = $response->headers->all();
        unset($headers['x-request-id'], $headers['date']);
        $answers[] = serialize([$response->getContent(), $headers]);
    }

    expect(array_unique($answers))->toHaveCount(1);
})->with(['GET', 'PATCH']);

it('REQ-8.5 checks authentication before existence', function (string $method): void {
    json($method, '/api/v1/books/'.Str::uuid())
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
})->with(['GET', 'PATCH']);
