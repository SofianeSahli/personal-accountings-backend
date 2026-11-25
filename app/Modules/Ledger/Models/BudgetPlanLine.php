<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

use App\Modules\Ledger\database\factories\BudgetPlanLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $budget_plan_id
 * @property string $category_id
 * @property int $planned
 * @property-read BudgetPlan $plan
 * @property-read Category $category
 */
#[Fillable(['budget_plan_id', 'category_id', 'planned'])]
final class BudgetPlanLine extends Model
{
    /** @use HasFactory<BudgetPlanLineFactory> */
    use HasFactory;

    use HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<BudgetPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function newFactory(): BudgetPlanLineFactory
    {
        return BudgetPlanLineFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned' => 'integer',
        ];
    }
}
