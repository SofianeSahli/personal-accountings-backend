<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\FieldError;
use App\Modules\Ledger\Support\TransactionFilters;
use App\Modules\Ledger\Support\TransactionPage;
use Illuminate\Database\Eloquent\Builder;

final class ListTransactions
{
    public const PER_PAGE = 25;

    private const CODE_SEPARATOR = '.';

    private const AFTER_CODE_SEPARATOR = '/';

    public function __construct(private readonly NameAccounts $nameAccounts) {}

    public function handle(Book $book, TransactionFilters $filters): TransactionPage
    {
        $query = Transaction::query()
            ->with('category')
            ->where('book_id', $book->id);

        if ($filters->from !== null) {
            $query->where('date', '>=', $filters->from);
        }

        if ($filters->to !== null) {
            $query->where('date', '<=', $filters->to);
        }

        if ($filters->authorId !== null) {
            $query->where('created_by', $filters->authorId);
        }

        if ($filters->type !== null) {
            $query->whereIn('category_id', Category::query()->select('id')->where('type', $filters->type->value));
        }

        if ($filters->categoryCode !== null) {
            $query->whereIn('category_id', $this->categoryAndBelow($filters->categoryCode));
        }

        $page = $query
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);

        return new TransactionPage($page, $this->nameAccounts->handle($page->items()));
    }

    /**
     * @return Builder<Category>
     */
    private function categoryAndBelow(string $code): Builder
    {
        if (! Category::query()->where('code', $code)->exists()) {
            throw FieldError::of('category', 'category.not_found');
        }

        return Category::query()
            ->select('id')
            ->where(fn (Builder $codes): Builder => $codes
                ->where('code', $code)
                ->orWhere(fn (Builder $below): Builder => $below
                    ->where('code', '>', $code.self::CODE_SEPARATOR)
                    ->where('code', '<', $code.self::AFTER_CODE_SEPARATOR)));
    }
}
