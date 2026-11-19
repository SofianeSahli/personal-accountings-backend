<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Book;

use function Pest\Laravel\patchJson;

it('REQ-5.1 renames a personal book and changes its timezone (token mode)', function (): void {
    $user = User::factory()->create();
    $book = Book::factory()->ownedByAccount($user)->create(['name' => 'Old', 'currency' => 'EUR']);

    patchJson("/api/v1/books/{$book->id}", ['name' => ' Courses ', 'timezone' => 'Europe/Paris'], HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.name', 'Courses')
        ->assertJsonPath('data.timezone', 'Europe/Paris')
        ->assertJsonPath('data.currency', ['code' => 'EUR', 'exponent' => 2]);
});

it('REQ-5.1 lets the household owner update the household book (cookie mode)', function (): void {
    $owner = User::factory()->create();
    $book = Book::factory()->ownedByHousehold(HouseholdScenario::ownedBy($owner)->id)->create(['name' => 'Old']);

    WebBrowser::signedInAs($owner)->patchJson("/api/v1/books/{$book->id}", ['name' => 'Foyer'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Foyer')
        ->assertJsonPath('data.can_manage', true);
});

it('REQ-5.1 leaves out fields unchanged, and an empty body changes nothing', function (array $body, string $name, string $timezone): void {
    $user = User::factory()->create();
    $book = Book::factory()->ownedByAccount($user)->create(['name' => 'Old', 'timezone' => 'Africa/Tunis']);

    patchJson("/api/v1/books/{$book->id}", $body, HouseholdScenario::as($user))->assertOk();

    expect($book->fresh()?->name)->toBe($name)
        ->and($book->fresh()?->timezone)->toBe($timezone);
})->with([
    'empty' => [[], 'Old', 'Africa/Tunis'],
    'name only' => [['name' => 'New'], 'New', 'Africa/Tunis'],
    'timezone only' => [['timezone' => 'UTC'], 'Old', 'UTC'],
]);

it('REQ-5.1 and REQ-5.2 validate the fields that are sent', function (array $body, string $field, string $code): void {
    $user = User::factory()->create();
    $book = Book::factory()->ownedByAccount($user)->create(['name' => 'Old']);

    patchJson("/api/v1/books/{$book->id}", $body, HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [$field => [$code]]]]);

    expect($book->fresh()?->only(['name', 'currency', 'owner_id']))->toBe(['name' => 'Old', 'currency' => 'TND', 'owner_id' => $user->id]);
})->with([
    'empty name' => [['name' => ''], 'name', 'name.required'],
    'long name' => [['name' => str_repeat('x', 61)], 'name', 'name.max'],
    'empty timezone' => [['timezone' => ''], 'timezone', 'timezone.required'],
    'bad timezone' => [['timezone' => 'Europe/Nowhere'], 'timezone', 'timezone.iana_timezone'],
    'currency' => [['currency' => 'EUR'], 'currency', 'currency.prohibited'],
    'household id' => [['household_id' => 'x'], 'household_id', 'household_id.prohibited'],
]);

it('REQ-5.3 refuses a household member whatever the body', function (array $body): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);
    $book = Book::factory()->ownedByHousehold($household->id)->create(['name' => 'Foyer']);

    patchJson("/api/v1/books/{$book->id}", $body, HouseholdScenario::as($member))
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'book.forbidden']]);

    expect($book->fresh()?->name)->toBe('Foyer');
})->with([
    'valid' => [['name' => 'Mine']],
    'invalid' => [['currency' => 'EUR']],
    'empty' => [[]],
]);

it('REQ-5.4 changes no stored data but the timezone', function (): void {
    $user = User::factory()->create();
    $book = Book::factory()->ownedByAccount($user)->create();
    $before = $book->fresh()?->only(['name', 'currency', 'owner_type', 'owner_id', 'created_at']);

    patchJson("/api/v1/books/{$book->id}", ['timezone' => 'Asia/Tokyo'], HouseholdScenario::as($user))->assertOk();

    expect($book->fresh()?->only(['name', 'currency', 'owner_type', 'owner_id', 'created_at']))->toEqual($before);
});
