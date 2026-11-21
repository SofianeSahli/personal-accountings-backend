<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

use App\Modules\Household\Support\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\database\factories\BookFactory;
use App\Shared\Money\Currency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'currency', 'timezone', 'owner_type', 'owner_id'])]
final class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    public const ACCOUNT_OWNER = 'account';

    public const HOUSEHOLD_OWNER = 'household';

    public function isPersonal(): bool
    {
        return $this->owner_type === self::ACCOUNT_OWNER;
    }

    public function currencyUnit(): Currency
    {
        return Currency::of($this->currency);
    }

    public function isReachableBy(User $user, ?Membership $membership): bool
    {
        if ($this->isPersonal()) {
            return $this->owner_id === $user->id;
        }

        return $membership !== null && $membership->householdId === $this->owner_id;
    }

    public function canBeManagedBy(User $user, ?Membership $membership): bool
    {
        if ($this->isPersonal()) {
            return $this->owner_id === $user->id;
        }

        return $this->isReachableBy($user, $membership) && $membership?->isOwner === true;
    }

    protected static function newFactory(): BookFactory
    {
        return BookFactory::new();
    }
}
