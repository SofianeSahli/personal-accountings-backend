<?php

declare(strict_types=1);

namespace App\Modules\Household\database\factories;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HouseholdMember>
 */
final class HouseholdMemberFactory extends Factory
{
    protected $model = HouseholdMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'user_id' => User::factory(),
            'role' => HouseholdRole::Member,
        ];
    }
}
