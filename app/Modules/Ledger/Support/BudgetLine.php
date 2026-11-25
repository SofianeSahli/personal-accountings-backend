<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\BudgetStatus;

final readonly class BudgetLine
{
    public function __construct(
        public string $category,
        public int $planned,
        public int $actual,
        public ?int $percent,
        public BudgetStatus $status,
        public bool $retired,
    ) {}

    public static function of(string $category, int $planned, int $actual, bool $retired): self
    {
        return new self(
            category: $category,
            planned: $planned,
            actual: $actual,
            percent: BudgetStatus::percentOf($planned, $actual),
            status: BudgetStatus::of($planned, $actual),
            retired: $retired,
        );
    }

    public function left(): int
    {
        return max($this->planned - $this->actual, 0);
    }
}
