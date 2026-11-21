<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Household\Support\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class BookPage
{
    /**
     * @param  CursorPaginator<int, Book>  $page
     */
    public function __construct(
        public CursorPaginator $page,
        public User $reader,
        public ?Membership $membership,
    ) {}

    public function canManage(Book $book): bool
    {
        return $book->canBeManagedBy($this->reader, $this->membership);
    }
}
