<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Support\HouseholdPage;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ListHouseholds
{
    public const PER_PAGE = 25;

    public function __construct(private readonly FindMembership $findMembership) {}

    public function handle(User $user): HouseholdPage
    {
        $page = Household::query()
            ->whereHas('members', fn (Builder $members): Builder => $members->where('user_id', $user->id))
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);

        return new HouseholdPage($page, $this->findMembership->handle($user));
    }
}
