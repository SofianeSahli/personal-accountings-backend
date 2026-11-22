<?php

declare(strict_types=1);

namespace App\Modules\Ledger\database\factories;

use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\CategoryLevel;
use App\Modules\Ledger\Models\CategoryType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    private const OTHER_SEGMENT = 'other';

    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'top_'.fake()->unique()->numberBetween(1, 1_000_000);

        return [
            'parent_id' => null,
            'code' => $code,
            'translation_key' => Category::translationKeyOf($code),
            'level' => CategoryLevel::Top,
            'type' => CategoryType::Expense,
            'emoji' => '🧺',
            'is_other' => false,
            'position' => 0,
            'retired_at' => null,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(fn (): array => [
            'code' => $code,
            'translation_key' => Category::translationKeyOf($code),
            'is_other' => substr_count($code, '.') === CategoryLevel::Leaf->value - 1
                && str_ends_with($code, '.'.self::OTHER_SEGMENT),
        ]);
    }

    public function under(Category $parent, string $segment, int $position = 0): static
    {
        return $this->code($parent->code.'.'.$segment)->state(fn (): array => [
            'parent_id' => $parent->id,
            'level' => CategoryLevel::from($parent->level->value + 1),
            'type' => $parent->type,
            'position' => $position,
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (): array => ['retired_at' => now()]);
    }
}
