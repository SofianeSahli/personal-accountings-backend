<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

use App\Modules\Ledger\database\factories\BudgetPlanFactory;
use App\Modules\Ledger\Support\BudgetMonth;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $book_id
 * @property string $month
 * @property ?int $expected_income
 * @property ?int $planned_saving
 * @property string $created_by
 * @property string $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, BudgetPlanLine> $lines
 */
#[Fillable(['book_id', 'month', 'expected_income', 'planned_saving', 'created_by', 'updated_by'])]
final class BudgetPlan extends Model
{
    /** @use HasFactory<BudgetPlanFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return Builder<self>
     */
    public static function of(Book $book, BudgetMonth $month): Builder
    {
        return self::query()->where('book_id', $book->id)->where('month', $month->first);
    }

    public function isEmpty(): bool
    {
        return $this->lines->isEmpty() && $this->expected_income === null && $this->planned_saving === null;
    }

    /**
     * @return HasMany<BudgetPlanLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetPlanLine::class);
    }

    protected static function newFactory(): BudgetPlanFactory
    {
        return BudgetPlanFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_income' => 'integer',
            'planned_saving' => 'integer',
        ];
    }
}
