<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\Accounts\FindAccountNames;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Str;

it('REQ-7.3 returns the current name of each known account by id', function (): void {
    $amira = User::factory()->create(['name' => 'Amira']);
    $sami = User::factory()->create(['name' => 'Sami']);
    User::factory()->create(['name' => 'Not asked']);
    $amira->update(['name' => 'Amira B.']);

    $names = app(FindAccountNames::class)->handle([$amira->id, $sami->id, (string) Str::uuid()]);

    expect($names)->toEqualCanonicalizing([$amira->id => 'Amira B.', $sami->id => 'Sami'])
        ->and(array_keys($names))->toEqualCanonicalizing([$amira->id, $sami->id]);
});

it('REQ-7.3 returns nothing for no ids', function (): void {
    expect(app(FindAccountNames::class)->handle([]))->toBe([]);
});
