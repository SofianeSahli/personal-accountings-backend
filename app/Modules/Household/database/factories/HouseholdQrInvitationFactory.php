<?php

declare(strict_types=1);

namespace App\Modules\Household\database\factories;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HouseholdQrInvitation>
 */
final class HouseholdQrInvitationFactory extends Factory
{
    protected $model = HouseholdQrInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'invited_by' => User::factory(),
            'token_hash' => HouseholdQrInvitation::hashOf(HouseholdQrInvitation::newToken()),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addMinutes(HouseholdQrInvitation::VALID_MINUTES),
        ];
    }

    public function withToken(string $token): static
    {
        return $this->state(fn (): array => ['token_hash' => HouseholdQrInvitation::hashOf($token)]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subSecond()]);
    }

    public function accepted(): static
    {
        return $this->closedAs(InvitationStatus::Accepted);
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
