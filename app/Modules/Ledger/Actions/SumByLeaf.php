<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\CategoryType;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\LeafTotal;
use App\Modules\Ledger\Support\SummaryPeriod;

final class SumByLeaf
{
    /**
     * @return list<LeafTotal>
     */
    public function handle(Book $book, SummaryPeriod $period): array
    {
        $rows = Transaction::query()
            ->where('transactions.book_id', $book->id)
            ->whereBetween('transactions.date', [$period->from, $period->to])
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->toBase()
            ->selectRaw('transactions.category_id as category_id, categories.type as type, COUNT(*) as entries, SUM(transactions.amount) as total')
            ->groupBy('transactions.category_id', 'categories.type')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[] = new LeafTotal(
                categoryId: (string) $row->category_id,
                type: CategoryType::from((string) $row->type),
                total: (int) $row->total,
                entries: (int) $row->entries,
            );
        }

        return $totals;
    }
}
