<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\CategoryLevel;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\FieldError;

final class SuggestOtherNames
{
    public const MAX_SUGGESTIONS = 10;

    public const USES_READ = 500;

    private const USED_AT_FORMAT = 'Y-m-d H:i:s.u';

    /**
     * @return list<string>
     */
    public function handle(Book $book, string $code): array
    {
        $category = Category::query()->where('code', $code)->first();

        if ($category === null) {
            throw FieldError::of('category', 'category.not_found');
        }

        if ($category->level !== CategoryLevel::Leaf || ! $category->is_other) {
            throw FieldError::of('category', 'category.not_other');
        }

        $groups = [];

        foreach ($this->latestUses($book, $category) as [$name, $usedAt]) {
            $key = mb_strtolower($name);
            $groups[$key] ??= ['name' => $name, 'count' => 0, 'last' => $usedAt];
            $groups[$key]['count']++;
        }

        usort($groups, fn (array $a, array $b): int => [$b['count'], $b['last']] <=> [$a['count'], $a['last']]);

        return array_slice(array_column($groups, 'name'), 0, self::MAX_SUGGESTIONS);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function latestUses(Book $book, Category $category): array
    {
        $uses = [];

        foreach ([Transaction::query(), RecurringRule::query()] as $query) {
            $rows = $query
                ->where('book_id', $book->id)
                ->where('category_id', $category->id)
                ->whereNotNull('other_name')
                ->orderByDesc('updated_at')
                ->limit(self::USES_READ)
                ->get(['other_name', 'updated_at']);

            foreach ($rows as $row) {
                $uses[] = [(string) $row->other_name, $row->updated_at->format(self::USED_AT_FORMAT)];
            }
        }

        usort($uses, fn (array $a, array $b): int => $b[1] <=> $a[1]);

        return array_slice($uses, 0, self::USES_READ);
    }
}
