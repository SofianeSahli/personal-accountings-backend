<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

use App\Modules\Ledger\database\factories\TransactionFactory;
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
 * @property ?string $recurring_rule_id
 * @property ?string $rule_due_on
 * @property int $amount
 * @property string $date
 * @property ?string $other_name
 * @property ?string $comment
 * @property string $created_by
 * @property string $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Category $category
 */
#[Fillable([
    'id', 'book_id', 'category_id', 'recurring_rule_id', 'rule_due_on', 'amount', 'date',
    'other_name', 'comment', 'created_by', 'updated_by',
])]
final class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
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

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }
}
