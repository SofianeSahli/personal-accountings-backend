<?php

declare(strict_types=1);

namespace App\Modules\Household\Models;

use App\Modules\Household\database\factories\HouseholdMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['household_id', 'user_id', 'role'])]
final class HouseholdMember extends Model
{
    /** @use HasFactory<HouseholdMemberFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $table = 'household_user';

    public function isOwner(): bool
    {
        return $this->role === HouseholdRole::Owner;
    }

    public function endMembership(): bool
    {
        return self::query()->whereKey($this->id)->update([$this->getDeletedAtColumn() => now()]) === 1;
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    protected static function newFactory(): HouseholdMemberFactory
    {
        return HouseholdMemberFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => HouseholdRole::class,
        ];
    }
}
