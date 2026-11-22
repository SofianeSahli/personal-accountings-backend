<?php

declare(strict_types=1);

use App\Modules\Household\Actions\DeleteHousehold;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;

it('REQ-8.5 counts as pending only open, unexpired invitations of a live household', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $pending = HouseholdScenario::invite($household, 'open@example.com');
    HouseholdScenario::invite($household, 'expired@example.com', HouseholdInvitation::factory()->expired());
    HouseholdScenario::invite($household, 'accepted@example.com', HouseholdInvitation::factory()->accepted());
    HouseholdScenario::invite($household, 'declined@example.com', HouseholdInvitation::factory()->declined());
    HouseholdScenario::invite($household, 'cancelled@example.com', HouseholdInvitation::factory()->cancelled());

    expect(HouseholdInvitation::query()->pending()->pluck('id')->all())->toBe([$pending->id]);
});

it('REQ-8.5 leaves out the invitations of a deleted household', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    HouseholdScenario::invite($household, 'open@example.com');

    $household->delete();

    expect(HouseholdInvitation::query()->pending()->count())->toBe(0)
        ->and(HouseholdInvitation::query()->count())->toBe(1);
});

it('REQ-1.6 compares stored emails exactly, accents included', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    HouseholdScenario::invite($household, 'andre@example.com');

    expect(HouseholdInvitation::query()->where('email', 'andré@example.com')->count())->toBe(0)
        ->and(HouseholdInvitation::query()->where('email', 'andre@example.com')->count())->toBe(1);
});

it('REQ-8.5 keeps invitation rows when the household is deleted', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    HouseholdScenario::invite($household, 'open@example.com');

    app(DeleteHousehold::class)->handle($household);

    expect(HouseholdInvitation::query()->count())->toBe(1);
});
