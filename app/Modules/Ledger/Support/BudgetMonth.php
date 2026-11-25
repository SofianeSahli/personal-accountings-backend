<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\Book;
use Carbon\CarbonImmutable;

final readonly class BudgetMonth
{
    public const FORMAT = 'Y-m';

    private const FIRST_DAY = '-01';

    private const KEY_LENGTH = 7;

    private function __construct(
        public string $key,
        public string $first,
        public string $last,
    ) {}

    public static function parse(string $key): self
    {
        return self::starting(BookDay::parse($key.self::FIRST_DAY));
    }

    public static function current(Book $book): self
    {
        return self::parse(substr(BookDay::today($book), 0, self::KEY_LENGTH));
    }

    public function previous(): self
    {
        return $this->plusMonths(-1);
    }

    public function plusMonths(int $months): self
    {
        return self::starting(BookDay::parse($this->first)->addMonthsNoOverflow($months));
    }

    public function period(): SummaryPeriod
    {
        return SummaryPeriod::range($this->first, $this->last);
    }

    private static function starting(CarbonImmutable $first): self
    {
        return new self($first->format(self::FORMAT), $first->toDateString(), $first->endOfMonth()->toDateString());
    }
}
