<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Identity\Actions\Accounts\FindAccountNames;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\CategoryType;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\CategoryTotal;
use App\Modules\Ledger\Support\MemberTotal;
use App\Modules\Ledger\Support\OtherNameTotal;
use App\Modules\Ledger\Support\Summary;
use App\Modules\Ledger\Support\SummaryPeriod;
use App\Modules\Ledger\Support\TypeTotals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SummarizePeriod
{
    private const MONTH_LENGTH = 7;

    private const ENTRIES = 'COUNT(*) as entries';

    private const TOTAL = 'SUM(transactions.amount) as total';

    public function __construct(
        private readonly FindAccountNames $findAccountNames,
        private readonly SumByLeaf $sumByLeaf,
    ) {}

    public function handle(Book $book, SummaryPeriod $period): Summary
    {
        $days = $this->days($book, $period);
        $categories = Category::query()->orderBy('position')->orderBy('code')->get()->keyBy('id');

        return new Summary(
            period: $period,
            currency: $book->currency,
            totals: array_reduce($days, fn (TypeTotals $sum, TypeTotals $day): TypeTotals => $this->add($sum, $day), TypeTotals::none()),
            categories: $this->categories($book, $period, $categories),
            members: $this->members($book, $period),
            days: $days,
            months: $this->months($days),
        );
    }

    /**
     * @return Builder<Transaction>
     */
    private function entries(Book $book, SummaryPeriod $period): Builder
    {
        return Transaction::query()
            ->where('transactions.book_id', $book->id)
            ->whereBetween('transactions.date', [$period->from, $period->to]);
    }

    /**
     * @return Builder<Transaction>
     */
    private function entriesByType(Book $book, SummaryPeriod $period): Builder
    {
        return $this->entries($book, $period)
            ->join('categories', 'categories.id', '=', 'transactions.category_id');
    }

    /**
     * @return array<string, TypeTotals>
     */
    private function days(Book $book, SummaryPeriod $period): array
    {
        $rows = $this->entriesByType($book, $period)
            ->toBase()
            ->selectRaw('transactions.date as day, categories.type as type, '.self::ENTRIES.', '.self::TOTAL)
            ->groupBy('transactions.date', 'categories.type')
            ->orderBy('transactions.date')
            ->get();

        $days = [];

        foreach ($rows as $row) {
            $day = (string) $row->day;
            $days[$day] = ($days[$day] ?? TypeTotals::none())
                ->plus(CategoryType::from((string) $row->type), (int) $row->total, (int) $row->entries);
        }

        return $days;
    }

    /**
     * @param  array<string, TypeTotals>  $days
     * @return array<string, TypeTotals>
     */
    private function months(array $days): array
    {
        $months = [];

        foreach ($days as $day => $totals) {
            $month = substr($day, 0, self::MONTH_LENGTH);
            $months[$month] = $this->add($months[$month] ?? TypeTotals::none(), $totals);
        }

        return $months;
    }

    private function add(TypeTotals $a, TypeTotals $b): TypeTotals
    {
        return new TypeTotals(
            income: $a->income + $b->income,
            expense: $a->expense + $b->expense,
            saving: $a->saving + $b->saving,
            entries: $a->entries + $b->entries,
        );
    }

    /**
     * @return list<MemberTotal>
     */
    private function members(Book $book, SummaryPeriod $period): array
    {
        $rows = $this->entriesByType($book, $period)
            ->toBase()
            ->selectRaw('transactions.created_by as account_id, categories.type as type, '.self::ENTRIES.', '.self::TOTAL)
            ->groupBy('transactions.created_by', 'categories.type')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $id = (string) $row->account_id;
            $totals[$id] = ($totals[$id] ?? TypeTotals::none())
                ->plus(CategoryType::from((string) $row->type), (int) $row->total, (int) $row->entries);
        }

        $names = $this->findAccountNames->handle(array_map('strval', array_keys($totals)));
        $members = [];

        foreach ($totals as $id => $memberTotals) {
            $members[] = new MemberTotal((string) $id, $names[$id] ?? null, $memberTotals);
        }

        usort($members, fn (MemberTotal $a, MemberTotal $b): int => [$b->totals->expense, $a->accountId] <=> [$a->totals->expense, $b->accountId]);

        return $members;
    }

    /**
     * @param  Collection<string, Category>  $categories
     * @return list<CategoryTotal>
     */
    private function categories(Book $book, SummaryPeriod $period, Collection $categories): array
    {
        $sums = [];

        foreach ($this->sumByLeaf->handle($book, $period) as $leaf) {
            $category = $categories->get($leaf->categoryId);

            while ($category !== null) {
                $sums[$category->id] ??= ['total' => 0, 'entries' => 0];
                $sums[$category->id]['total'] += $leaf->total;
                $sums[$category->id]['entries'] += $leaf->entries;
                $category = $category->parent_id === null ? null : $categories->get($category->parent_id);
            }
        }

        return $this->level(null, $categories, $sums, $this->otherNames($book, $period));
    }

    /**
     * @param  Collection<string, Category>  $categories
     * @param  array<string, array{total: int, entries: int}>  $sums
     * @param  array<string, list<OtherNameTotal>>  $otherNames
     * @return list<CategoryTotal>
     */
    private function level(?string $parentId, Collection $categories, array $sums, array $otherNames): array
    {
        $nodes = [];

        foreach ($categories as $category) {
            if ($category->parent_id !== $parentId || ! isset($sums[$category->id])) {
                continue;
            }

            $nodes[] = new CategoryTotal(
                code: $category->code,
                type: $category->type,
                total: $sums[$category->id]['total'],
                entries: $sums[$category->id]['entries'],
                children: $this->level($category->id, $categories, $sums, $otherNames),
                otherNames: $otherNames[$category->id] ?? [],
            );
        }

        return $nodes;
    }

    /**
     * @return array<string, list<OtherNameTotal>>
     */
    private function otherNames(Book $book, SummaryPeriod $period): array
    {
        $rows = $this->entries($book, $period)
            ->whereNotNull('transactions.other_name')
            ->toBase()
            ->selectRaw('transactions.category_id as category_id, ANY_VALUE(transactions.other_name) as name, MAX(transactions.updated_at) as last_typed, '.self::ENTRIES.', '.self::TOTAL)
            ->groupBy('transactions.category_id', DB::raw('transactions.other_name COLLATE utf8mb4_bin'))
            ->get();

        $groups = [];

        foreach ($rows as $row) {
            $leaf = (string) $row->category_id;
            $name = (string) $row->name;
            $last = (string) $row->last_typed;
            $key = mb_strtolower($name);
            $group = $groups[$leaf][$key] ?? ['name' => $name, 'last' => $last, 'total' => 0, 'entries' => 0];

            if ($last > $group['last']) {
                $group['name'] = $name;
                $group['last'] = $last;
            }

            $group['total'] += (int) $row->total;
            $group['entries'] += (int) $row->entries;
            $groups[$leaf][$key] = $group;
        }

        $names = [];

        foreach ($groups as $leaf => $leafGroups) {
            $list = array_map(
                fn (array $group): OtherNameTotal => new OtherNameTotal($group['name'], $group['total'], $group['entries']),
                array_values($leafGroups),
            );
            usort($list, fn (OtherNameTotal $a, OtherNameTotal $b): int => [$b->total, $a->name] <=> [$a->total, $b->name]);
            $names[(string) $leaf] = $list;
        }

        return $names;
    }
}
