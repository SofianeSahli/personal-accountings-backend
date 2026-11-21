<?php

declare(strict_types=1);

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;

it('REQ-1.4 creates no household when an account is created or signs in', function (): void {
    IdentityScenario::signUpAndSignInEveryWay();

    expect(User::query()->count())->toBe(2)
        ->and(Household::withTrashed()->count())->toBe(0)
        ->and(HouseholdMember::withTrashed()->count())->toBe(0);
});
