<?php

declare(strict_types=1);

namespace App\Modules\Household\Models;

use App\Modules\Household\database\factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name'])]
final class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    public const MORPH_ALIAS = 'household';

    public const MEMBER_LIMIT = 10;

    public static function lockLive(string $id): ?self
    {
        return self::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function memberCount(): int
    {
        return $this->members()->count();
    }

    /**
     * @return HasMany<HouseholdMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    protected static function newFactory(): HouseholdFactory
    {
        return HouseholdFactory::new();
    }
}
