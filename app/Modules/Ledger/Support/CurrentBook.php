<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\Book;

final readonly class CurrentBook
{
    public function __construct(
        public Book $book,
        public bool $canManage,
    ) {}
}
