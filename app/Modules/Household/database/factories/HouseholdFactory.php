<?php

declare(strict_types=1);

namespace App\Modules\Household\database\factories;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
final class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Foyer '.fake()->lastName(),
        ];
    }

    public function ownedBy(User $owner): static
    {
        return $this->withMember($owner, HouseholdRole::Owner);
    }

    public function withMember(User $user, HouseholdRole $role = HouseholdRole::Member): static
    {
        return $this->afterCreating(function (Household $household) use ($user, $role): void {
            HouseholdMember::factory()->create([
                'household_id' => $household->id,
                'user_id' => $user->id,
                'role' => $role,
            ]);
        });
    }
}
