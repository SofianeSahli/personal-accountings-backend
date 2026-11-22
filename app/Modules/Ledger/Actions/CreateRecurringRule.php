<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Support\CategoryChoice;
use App\Modules\Ledger\Support\NewRecurringRule;
use App\Modules\Ledger\Support\RecurringRuleView;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class CreateRecurringRule
{
    public const RULES_PER_BOOK = 100;

    public function __construct(
        private readonly ChooseCategory $chooseCategory,
        private readonly CatchUpRecurringRule $catchUp,
        private readonly NameAccounts $nameAccounts,
    ) {}

    public function handle(Book $book, User $user, NewRecurringRule $new): RecurringRuleView
    {
        $chosen = $this->chooseCategory->handle(CategoryChoice::forNew($new->categoryCode, $new->otherName));

        $rule = DB::transaction(function () use ($book, $user, $new, $chosen): RecurringRule {
            Book::query()->whereKey($book->id)->lockForUpdate()->first();

            if (RecurringRule::query()->where('book_id', $book->id)->count() >= self::RULES_PER_BOOK) {
                throw new ApiException('recurring_rule.limit_reached', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $rule = RecurringRule::query()->create([
                'book_id' => $book->id,
                'category_id' => $chosen->category->id,
                'amount' => $new->amount,
                'other_name' => $chosen->otherName,
                'comment' => $new->comment,
                'frequency' => $new->frequency,
                'interval' => $new->interval,
                'starts_on' => $new->startsOn,
                'ends_on' => $new->endsOn,
                'next_due_on' => $new->startsOn,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->catchUp->handle($rule);

            return $rule;
        });

        $rule->setRelation('category', $chosen->category);

        return new RecurringRuleView($rule, $this->nameAccounts->handle([$rule]));
    }
}
