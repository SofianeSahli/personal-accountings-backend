<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Transaction;
use Illuminate\Auth\Access\Response;

final class TransactionPolicy
{
    public function access(User $user, ?Transaction $transaction, Book $book): Response
    {
        return $transaction !== null && $transaction->book_id === $book->id
            ? Response::allow()
            : Response::denyAsNotFound(code: 'transaction.not_found');
    }
}
