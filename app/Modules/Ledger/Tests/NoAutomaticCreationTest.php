<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;
use App\Modules\Ledger\Models\Book;

use function Pest\Laravel\postJson;

it('REQ-1.4 creates no book when an account is created or signs in', function (): void {
    IdentityScenario::signUpAndSignInEveryWay();

    expect(User::query()->count())->toBe(2)
        ->and(Book::withTrashed()->count())->toBe(0);
});

it('REQ-2.1 creates no book with a household', function (): void {
    $user = User::factory()->create();

    postJson('/api/v1/households', ['name' => 'Foyer'], IdentityScenario::bearer(IdentityScenario::tokenFor($user)))
        ->assertCreated();

    expect(Book::withTrashed()->count())->toBe(0);
});
