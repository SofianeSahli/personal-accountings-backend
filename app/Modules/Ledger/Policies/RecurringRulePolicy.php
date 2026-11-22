<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\RecurringRule;
use Illuminate\Auth\Access\Response;

final class RecurringRulePolicy
{
    public function access(User $user, ?RecurringRule $rule, Book $book): Response
    {
        return $rule !== null && $rule->book_id === $book->id
            ? Response::allow()
            : Response::denyAsNotFound(code: 'recurring_rule.not_found');
    }
}
