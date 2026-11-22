<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

use App\Modules\Ledger\database\factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property ?string $parent_id
 * @property string $code
 * @property string $translation_key
 * @property CategoryLevel $level
 * @property CategoryType $type
 * @property ?string $emoji
 * @property bool $is_other
 * @property int $position
 * @property ?Carbon $retired_at
 */
#[Fillable(['parent_id', 'code', 'translation_key', 'level', 'type', 'emoji', 'is_other', 'position', 'retired_at'])]
final class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasUuids;

    public const TRANSLATION_PREFIX = 'categories.';

    public const TRANSLATION_SUFFIX = '.label';

    public static function translationKeyOf(string $code): string
    {
        return self::TRANSLATION_PREFIX.$code.self::TRANSLATION_SUFFIX;
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => CategoryLevel::class,
            'type' => CategoryType::class,
            'is_other' => 'boolean',
            'position' => 'integer',
            'retired_at' => 'datetime',
        ];
    }
}
