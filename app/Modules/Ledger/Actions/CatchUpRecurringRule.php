<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\BookDay;
use Illuminate\Support\Facades\DB;

final class CatchUpRecurringRule
{
    public function handle(RecurringRule $rule): int
    {
        return DB::transaction(function () use ($rule): int {
            $locked = RecurringRule::query()->with('book')->whereKey($rule->id)->lockForUpdate()->first();

            if ($locked === null || $locked->book === null) {
                return 0;
            }

            $today = BookDay::today($locked->book);
            $schedule = $locked->schedule();
            $made = 0;

            while ($locked->next_due_on !== null && $locked->next_due_on <= $today) {
                $dueOn = $locked->next_due_on;

                if (! $this->hasEntryFor($locked, $dueOn)) {
                    $this->record($locked, $dueOn);
                    $made++;
                }

                $locked->next_due_on = $schedule->firstAfter($dueOn);
            }

            if ($locked->isDirty()) {
                $locked->timestamps = false;
                $locked->save();
            }

            $rule->next_due_on = $locked->next_due_on;
            $rule->syncOriginalAttribute('next_due_on');

            return $made;
        });
    }

    private function hasEntryFor(RecurringRule $rule, string $dueOn): bool
    {
        return Transaction::withTrashed()
            ->where('recurring_rule_id', $rule->id)
            ->where('rule_due_on', $dueOn)
            ->exists();
    }

    private function record(RecurringRule $rule, string $dueOn): void
    {
        Transaction::query()->create([
            'book_id' => $rule->book_id,
            'category_id' => $rule->category_id,
            'recurring_rule_id' => $rule->id,
            'rule_due_on' => $dueOn,
            'amount' => $rule->amount,
            'date' => $dueOn,
            'other_name' => $rule->other_name,
            'comment' => $rule->comment,
            'created_by' => $rule->created_by,
            'updated_by' => $rule->created_by,
        ]);
    }
}
