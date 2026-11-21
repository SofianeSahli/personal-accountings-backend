<?php

declare(strict_types=1);

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('REQ-8.6 lets the database refuse a second live membership for one user', function (): void {
    $user = User::factory()->create();
    HouseholdMember::factory()->create(['user_id' => $user->id, 'role' => HouseholdRole::Owner]);

    expect(fn () => HouseholdMember::factory()->create(['user_id' => $user->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('REQ-2.10 no longer counts a deleted membership', function (): void {
    $user = User::factory()->create();
    HouseholdMember::factory()->create(['user_id' => $user->id, 'role' => HouseholdRole::Owner])->delete();

    HouseholdMember::factory()->create(['user_id' => $user->id, 'role' => HouseholdRole::Owner]);

    expect(HouseholdMember::withTrashed()->where('user_id', $user->id)->count())->toBe(2);
});

it('REQ-2.12 keeps a deleted household in the database', function (): void {
    $household = Household::factory()->create();
    $household->delete();

    expect(Household::query()->find($household->id))->toBeNull()
        ->and(Household::withTrashed()->find($household->id)?->deleted_at)->not->toBeNull();
});
