<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Policies;

use App\Modules\Household\Actions\FindMembership;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use Illuminate\Auth\Access\Response;

final class BookPolicy
{
    public function __construct(private readonly FindMembership $findMembership) {}

    public function view(User $user, ?Book $book): Response
    {
        return $book !== null && $book->isReachableBy($user, $this->findMembership->handle($user))
            ? Response::allow()
            : $this->notFound();
    }

    public function update(User $user, ?Book $book): Response
    {
        $membership = $this->findMembership->handle($user);

        return match (true) {
            $book === null, ! $book->isReachableBy($user, $membership) => $this->notFound(),
            $book->canBeManagedBy($user, $membership) => Response::allow(),
            default => Response::deny(code: 'book.forbidden'),
        };
    }

    private function notFound(): Response
    {
        return Response::denyAsNotFound(code: 'book.not_found');
    }
}
