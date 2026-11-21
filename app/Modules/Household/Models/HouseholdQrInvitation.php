<?php

declare(strict_types=1);

namespace App\Modules\Household\Models;

use App\Modules\Household\database\factories\HouseholdQrInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['household_id', 'invited_by', 'token_hash', 'status', 'expires_at', 'closed_at'])]
#[Hidden(['token_hash'])]
final class HouseholdQrInvitation extends Model
{
    /** @use HasFactory<HouseholdQrInvitationFactory> */
    use HasFactory;

    use HasUuids;

    public const VALID_MINUTES = 10;

    private const TOKEN_BYTES = 32;

    private const TOKEN_HASH_ALGORITHM = 'sha256';

    protected $table = 'household_qr_invitations';

    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');
    }

    public static function hashOf(string $token): string
    {
        return hash(self::TOKEN_HASH_ALGORITHM, $token);
    }

    public static function findByToken(string $token): ?self
    {
        return self::query()->where('token_hash', self::hashOf($token))->first();
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

    protected static function newFactory(): HouseholdQrInvitationFactory
    {
        return HouseholdQrInvitationFactory::new();
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
