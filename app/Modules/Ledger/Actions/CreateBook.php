<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Household\Actions\FindMembership;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Support\NewBook;
use App\Shared\Errors\ApiException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class CreateBook
{
    public const PERSONAL_BOOKS_LIMIT = 5;

    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(private readonly FindMembership $findMembership) {}

    public function handle(User $user, NewBook $newBook): Book
    {
        return $newBook->householdId === null
            ? $this->createPersonalBook($user, $newBook)
            : $this->createHouseholdBook($user, $newBook, $newBook->householdId);
    }

    private function createPersonalBook(User $user, NewBook $newBook): Book
    {
        $lock = Cache::lock('ledger:personal-books:'.$user->id, self::LOCK_SECONDS);

        try {
            return $lock->block(self::LOCK_WAIT_SECONDS, function () use ($user, $newBook): Book {
                $owned = Book::query()
                    ->where('owner_type', Book::ACCOUNT_OWNER)
                    ->where('owner_id', $user->id)
                    ->count();

                if ($owned >= self::PERSONAL_BOOKS_LIMIT) {
                    throw new ApiException('book.limit_reached', Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                return $this->store($newBook, Book::ACCOUNT_OWNER, $user->id);
            });
        } catch (LockTimeoutException) {
            throw new ApiException('server.unavailable', Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    private function createHouseholdBook(User $user, NewBook $newBook, string $householdId): Book
    {
        $membership = $this->findMembership->handle($user);

        if ($membership === null || $membership->householdId !== $householdId) {
            throw new ApiException(
                'validation.failed',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                fields: ['household_id' => ['household_id.not_found']],
            );
        }

        if (! $membership->isOwner) {
            throw new ApiException('household.forbidden', Response::HTTP_FORBIDDEN);
        }

        $hasBook = Book::query()
            ->where('owner_type', Book::HOUSEHOLD_OWNER)
            ->where('owner_id', $householdId)
            ->exists();

        if ($hasBook) {
            throw $this->householdHasBook();
        }

        try {
            return $this->store($newBook, Book::HOUSEHOLD_OWNER, $householdId);
        } catch (UniqueConstraintViolationException) {
            throw $this->householdHasBook();
        }
    }

    private function store(NewBook $newBook, string $ownerType, string $ownerId): Book
    {
        return Book::query()->create([
            'name' => $newBook->name,
            'currency' => $newBook->currency->code,
            'timezone' => $newBook->timezone,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ]);
    }

    private function householdHasBook(): ApiException
    {
        return new ApiException('book.household_has_book', Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
