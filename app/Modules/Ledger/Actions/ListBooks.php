<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Household\Actions\FindMembership;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Support\BookPage;
use Illuminate\Database\Eloquent\Builder;

final class ListBooks
{
    public const PER_PAGE = 25;

    public function __construct(private readonly FindMembership $findMembership) {}

    public function handle(User $user): BookPage
    {
        $membership = $this->findMembership->handle($user);

        $page = Book::query()
            ->where(function (Builder $owners) use ($user, $membership): void {
                $owners->where(fn (Builder $mine): Builder => $mine
                    ->where('owner_type', Book::ACCOUNT_OWNER)
                    ->where('owner_id', $user->id));

                if ($membership !== null) {
                    $owners->orWhere(fn (Builder $household): Builder => $household
                        ->where('owner_type', Book::HOUSEHOLD_OWNER)
                        ->where('owner_id', $membership->householdId));
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);

        return new BookPage($page, $user, $membership);
    }
}
