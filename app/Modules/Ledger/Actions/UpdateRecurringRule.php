<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Support\BookDay;
use App\Modules\Ledger\Support\CategoryChoice;
use App\Modules\Ledger\Support\FieldError;
use App\Modules\Ledger\Support\RecurringRuleChanges;
use App\Modules\Ledger\Support\RecurringRuleView;
use Illuminate\Support\Facades\DB;

final class UpdateRecurringRule
{
    private const SCHEDULE_FIELDS = ['frequency', 'interval', 'starts_on', 'ends_on'];

    public function __construct(
        private readonly ChooseCategory $chooseCategory,
        private readonly CatchUpRecurringRule $catchUp,
        private readonly NameAccounts $nameAccounts,
    ) {}

    public function handle(Book $book, RecurringRule $rule, User $user, RecurringRuleChanges $changes): RecurringRuleView
    {
        $updated = DB::transaction(function () use ($book, $rule, $user, $changes): RecurringRule {
            $this->catchUp->handle($rule);
            $rule->refresh();

            $chosen = $this->chooseCategory->handle(new CategoryChoice(
                code: $changes->categoryCode,
                sendsOtherName: $changes->sendsOtherName,
                otherName: $changes->otherName,
                current: $rule->category,
                currentOtherName: $rule->other_name,
            ));

            $rule->category_id = $chosen->category->id;
            $rule->other_name = $chosen->otherName;
            $rule->amount = $changes->amount ?? $rule->amount;
            $rule->frequency = $changes->frequency ?? $rule->frequency;
            $rule->interval = $changes->interval ?? $rule->interval;
            $rule->starts_on = $changes->startsOn ?? $rule->starts_on;

            if ($changes->sendsComment) {
                $rule->comment = $changes->comment;
            }

            if ($changes->sendsEndsOn) {
                $rule->ends_on = $changes->endsOn;
            }

            if ($rule->ends_on !== null && $rule->ends_on < $rule->starts_on) {
                throw FieldError::of('ends_on', 'ends_on.after_or_equal');
            }

            if ($rule->isDirty(self::SCHEDULE_FIELDS)) {
                $rule->next_due_on = $rule->schedule()->firstOnOrAfter(BookDay::today($book));
            }

            $rule->updated_by = $user->id;
            $rule->save();
            $this->catchUp->handle($rule);
            $rule->setRelation('category', $chosen->category);

            return $rule;
        });

        return new RecurringRuleView($updated, $this->nameAccounts->handle([$updated]));
    }
}
