<?php

declare(strict_types=1);

namespace App\Modules\Household\Models;

use App\Modules\Household\database\factories\HouseholdInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['household_id', 'email', 'invited_by', 'status', 'expires_at', 'closed_at'])]
final class HouseholdInvitation extends Model
{
    /** @use HasFactory<HouseholdInvitationFactory> */
    use HasFactory;

    use HasUuids;

    public const VALID_DAYS = 7;

    public const PENDING_LIMIT = 10;

    public const DAILY_LIMIT = 20;

    public const DAILY_WINDOW_HOURS = 24;

    protected $table = 'household_invitations';

    public static function findByRawId(string $id): ?self
    {
        return Str::isUuid($id) ? self::query()->find($id) : null;
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->whereHas('household');
    }

    protected static function newFactory(): HouseholdInvitationFactory
    {
        return HouseholdInvitationFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
