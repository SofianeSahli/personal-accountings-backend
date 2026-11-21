<?php

declare(strict_types=1);

namespace App\Modules\Household\database\factories;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HouseholdInvitation>
 */
final class HouseholdInvitationFactory extends Factory
{
    protected $model = HouseholdInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'email' => fake()->unique()->safeEmail(),
            'invited_by' => User::factory(),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays(HouseholdInvitation::VALID_DAYS),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subSecond()]);
    }

    public function accepted(): static
    {
        return $this->closedAs(InvitationStatus::Accepted);
    }

    public function declined(): static
    {
        return $this->closedAs(InvitationStatus::Declined);
    }

    public function cancelled(): static
    {
        return $this->closedAs(InvitationStatus::Cancelled);
    }

    private function closedAs(InvitationStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status, 'closed_at' => now()]);
    }
}
