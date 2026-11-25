<?php

declare(strict_types=1);

namespace App\Modules\Ledger\database\factories;

use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Models\BudgetPlanLine;
use App\Modules\Ledger\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetPlanLine>
 */
final class BudgetPlanLineFactory extends Factory
{
    protected $model = BudgetPlanLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_plan_id' => BudgetPlan::factory(),
            'category_id' => fn (): string => Category::factory()->create()->id,
            'planned' => fake()->numberBetween(0, 1_000_000),
        ];
    }

    public function inPlan(BudgetPlan $plan): static
    {
        return $this->state(fn (): array => ['budget_plan_id' => $plan->id]);
    }

    public function on(Category $category, int $planned): static
    {
        return $this->state(fn (): array => ['category_id' => $category->id, 'planned' => $planned]);
    }
}
