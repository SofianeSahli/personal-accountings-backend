<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\Book;

final readonly class SummaryPeriod
{
    private const FIRST_DAY = '-01';

    public function __construct(
        public string $from,
        public string $to,
    ) {}

    public static function month(string $month): self
    {
        $first = BookDay::parse($month.self::FIRST_DAY);

        return new self($first->toDateString(), $first->endOfMonth()->toDateString());
    }

    public static function thisMonth(Book $book): self
    {
        return self::month(substr(BookDay::today($book), 0, 7));
    }

    public static function range(string $from, string $to): self
    {
        return new self($from, $to);
    }
}
