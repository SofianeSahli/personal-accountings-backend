<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\CategoryType;

final readonly class TypeTotals
{
    public function __construct(
        public int $income,
        public int $expense,
        public int $saving,
        public int $entries,
    ) {}

    public static function none(): self
    {
        return new self(0, 0, 0, 0);
    }

    public function plus(CategoryType $type, int $amount, int $entries): self
    {
        return new self(
            income: $this->income + ($type === CategoryType::Income ? $amount : 0),
            expense: $this->expense + ($type === CategoryType::Expense ? $amount : 0),
            saving: $this->saving + ($type === CategoryType::Saving ? $amount : 0),
            entries: $this->entries + $entries,
        );
    }

    public function balance(): int
    {
        return $this->income - $this->expense - $this->saving;
    }
}
