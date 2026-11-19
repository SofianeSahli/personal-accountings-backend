<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Support\BookChanges;

final class UpdateBook
{
    public function handle(Book $book, BookChanges $changes): Book
    {
        $attributes = $changes->toAttributes();

        if ($attributes !== []) {
            $book->update($attributes);
        }

        return $book;
    }
}
