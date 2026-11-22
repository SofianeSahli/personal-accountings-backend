<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Support\RecurringRulePage;

final class ListRecurringRules
{
    public const PER_PAGE = 25;

    public function __construct(private readonly NameAccounts $nameAccounts) {}

    public function handle(Book $book): RecurringRulePage
    {
        $page = RecurringRule::query()
            ->with('category')
            ->where('book_id', $book->id)
            ->orderBy('due_order')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);

        return new RecurringRulePage($page, $this->nameAccounts->handle($page->items()));
    }
}
