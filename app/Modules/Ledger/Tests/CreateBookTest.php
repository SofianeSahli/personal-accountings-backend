<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Book;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

it('REQ-1.1 creates a personal book owned by the caller (token mode)', function (): void {
    $user = User::factory()->create();

    $response = postJson('/api/v1/books', [
        'name' => ' Mes dépenses ',
        'currency' => 'eur',
        'timezone' => 'Europe/Paris',
    ], HouseholdScenario::as($user));

    $book = Book::query()->sole();
    $response->assertCreated()->assertExactJson(['data' => [
        'id' => $book->id,
        'name' => 'Mes dépenses',
        'currency' => ['code' => 'EUR', 'exponent' => 2],
        'timezone' => 'Europe/Paris',
        'owner' => ['type' => 'account', 'id' => $user->id],
        'can_manage' => true,
    ]]);
    expect($book->currency)->toBe('EUR');
});

it('REQ-1.1 creates a personal book in cookie mode', function (): void {
    $user = User::factory()->create();

    WebBrowser::signedInAs($user)->postJson('/api/v1/books', ['name' => 'Perso'])
        ->assertCreated()
        ->assertJsonPath('data.owner', ['type' => 'account', 'id' => $user->id]);
});

it('REQ-1.2 defaults to TND and Africa/Tunis', function (array $body): void {
    postJson('/api/v1/books', ['name' => 'Perso', ...$body], HouseholdScenario::as(User::factory()->create()))
        ->assertCreated()
        ->assertJsonPath('data.currency', ['code' => 'TND', 'exponent' => 3])
        ->assertJsonPath('data.timezone', 'Africa/Tunis');
})->with([
    'left out' => [[]],
    'null' => [['currency' => null, 'timezone' => null]],
]);

it('REQ-0.2 and REQ-0.3 validate currency and timezone', function (array $body, string $field, string $code): void {
    postJson('/api/v1/books', ['name' => 'Perso', ...$body], HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [$field => [$code]]]]);

    expect(Book::query()->count())->toBe(0);
})->with([
    'unknown currency' => [['currency' => 'ABC'], 'currency', 'currency.supported_currency'],
    'currency too long' => [['currency' => 'EURO'], 'currency', 'currency.supported_currency'],
    'currency not a string' => [['currency' => 12], 'currency', 'currency.string'],
    'unknown timezone' => [['timezone' => 'Mars/Olympus'], 'timezone', 'timezone.iana_timezone'],
    'timezone in wrong case' => [['timezone' => 'africa/tunis'], 'timezone', 'timezone.iana_timezone'],
    'timezone not a string' => [['timezone' => 1], 'timezone', 'timezone.string'],
    'household id not a string' => [['household_id' => 7], 'household_id', 'household_id.string'],
]);

it('REQ-0.1 validates the name', function (mixed $name, string $code): void {
    postJson('/api/v1/books', ['name' => $name], HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['name' => [$code]]]]);
})->with([
    'missing' => [null, 'name.required'],
    'blank' => ['  ', 'name.required'],
    'not a string' => [true, 'name.string'],
    'too long' => [str_repeat('x', 61), 'name.max'],
]);

it('REQ-1.3 refuses a sixth personal book', function (): void {
    $user = User::factory()->create();
    Book::factory()->count(5)->ownedByAccount($user)->create();

    postJson('/api/v1/books', ['name' => 'Sixth'], HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'book.limit_reached']]);

    expect(Book::query()->count())->toBe(5);
});

it('REQ-1.3 does not count the household book, and REQ-8.9 does not count a deleted book', function (): void {
    $user = User::factory()->create();
    $household = HouseholdScenario::ownedBy($user);
    Book::factory()->ownedByHousehold($household->id)->create();
    Book::factory()->count(4)->ownedByAccount($user)->create();
    Book::factory()->ownedByAccount($user)->create()->delete();

    postJson('/api/v1/books', ['name' => 'Fifth'], HouseholdScenario::as($user))->assertCreated();
});

it('REQ-2.3 lets the household owner create its book', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    $response = postJson('/api/v1/books', [
        'name' => 'Dépenses du foyer',
        'household_id' => $household->id,
    ], HouseholdScenario::as($owner));

    $response->assertCreated()
        ->assertJsonPath('data.owner', ['type' => 'household', 'id' => $household->id])
        ->assertJsonPath('data.can_manage', true);
    expect(Book::query()->sole()->owner_type)->toBe('household');
});

it('REQ-2.3 answers the same household_id.not_found for an unknown, malformed or foreign household', function (): void {
    $user = User::factory()->create();
    HouseholdScenario::ownedBy($user);
    $foreign = HouseholdScenario::ownedBy(User::factory()->create());

    foreach ([(string) Str::uuid(), 'not-a-uuid', $foreign->id] as $householdId) {
        postJson('/api/v1/books', ['name' => 'Book', 'household_id' => $householdId], HouseholdScenario::as($user))
            ->assertStatus(422)
            ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['household_id' => ['household_id.not_found']]]]);
    }

    expect(Book::query()->count())->toBe(0);
});

it('REQ-2.3 refuses a member of the household', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    postJson('/api/v1/books', ['name' => 'Book', 'household_id' => $household->id], HouseholdScenario::as($member))
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'household.forbidden']]);
});

it('REQ-2.3 refuses a second book for one household', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    Book::factory()->ownedByHousehold($household->id)->create();

    postJson('/api/v1/books', ['name' => 'Again', 'household_id' => $household->id], HouseholdScenario::as($owner))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'book.household_has_book']]);

    expect(Book::query()->count())->toBe(1);
});

it('REQ-2.3 keeps one book per household when a concurrent request wins the race', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    Book::creating(function (Book $book) use ($household): void {
        if ($book->name !== 'Rival') {
            Book::factory()->ownedByHousehold($household->id)->create(['name' => 'Rival']);
        }
    });

    postJson('/api/v1/books', ['name' => 'Mine', 'household_id' => $household->id], HouseholdScenario::as($owner))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'book.household_has_book']]);

    expect(Book::query()->pluck('name')->all())->toBe(['Rival']);
});

it('REQ-8.1 refuses an anonymous caller', function (): void {
    postJson('/api/v1/books', ['name' => 'Book'])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('REQ-2.8 counts household and book creations together, 10 a minute', function (): void {
    $headers = HouseholdScenario::as(User::factory()->create());

    for ($attempt = 0; $attempt < 5; $attempt++) {
        expect(postJson('/api/v1/households', ['name' => 'Foyer'], $headers)->status())->not->toBe(429);
        expect(postJson('/api/v1/books', ['name' => 'Book'], $headers)->status())->not->toBe(429);
    }

    postJson('/api/v1/books', ['name' => 'Book'], $headers)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});
