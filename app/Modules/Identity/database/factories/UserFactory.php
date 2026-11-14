<?php

declare(strict_types=1);

namespace App\Modules\Identity\database\factories;

use App\Modules\Identity\Models\Locale;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    public const PASSWORD = 'password';

    private const REMEMBER_TOKEN_LENGTH = 10;

    protected $model = User::class;

    private static ?string $passwordHash = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$passwordHash ??= Hash::make(self::PASSWORD),
            'remember_token' => Str::random(self::REMEMBER_TOKEN_LENGTH),
            'locale' => Locale::Fr,
            'terms_version' => config('identity.terms_version'),
            'terms_accepted_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (): array => ['two_factor_enabled_at' => now()]);
    }

    public function googleOnly(): static
    {
        return $this->state(fn (): array => [
            'password' => null,
            'google_id' => (string) fake()->unique()->randomNumber(9, true),
        ]);
    }
}
