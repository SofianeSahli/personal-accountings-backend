<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

use App\Modules\Ledger\database\factories\RecurringRuleFactory;
use App\Modules\Ledger\Support\Schedule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $book_id
 * @property string $category_id
 * @property int $amount
 * @property ?string $other_name
 * @property ?string $comment
 * @property Frequency $frequency
 * @property int $interval
 * @property string $starts_on
 * @property ?string $ends_on
 * @property ?string $next_due_on
 * @property string $created_by
 * @property string $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Category $category
 * @property-read ?Book $book
 */
#[Fillable([
    'book_id', 'category_id', 'amount', 'other_name', 'comment', 'frequency', 'interval',
    'starts_on', 'ends_on', 'next_due_on', 'created_by', 'updated_by',
])]
final class RecurringRule extends Model
{
    /** @use HasFactory<RecurringRuleFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    public static function inBook(string $id, Book $book): ?self
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return self::query()->whereKey($id)->where('book_id', $book->id)->first();
    }

    public function schedule(): Schedule
    {
        return new Schedule($this->frequency, $this->interval, $this->starts_on, $this->ends_on);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    protected static function newFactory(): RecurringRuleFactory
    {
        return RecurringRuleFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'frequency' => Frequency::class,
            'interval' => 'integer',
        ];
    }
}
