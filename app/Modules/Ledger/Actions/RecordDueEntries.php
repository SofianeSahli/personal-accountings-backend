<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Support\DueEntriesReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecordDueEntries
{
    private const DAYS_AHEAD_OF_UTC = 1;

    public function __construct(private readonly CatchUpRecurringRule $catchUp) {}

    public function handle(): DueEntriesReport
    {
        $latestToday = CarbonImmutable::now('UTC')->addDays(self::DAYS_AHEAD_OF_UTC)->toDateString();
        $rules = 0;
        $entries = 0;
        $failures = 0;

        $due = RecurringRule::query()
            ->whereNotNull('next_due_on')
            ->where('next_due_on', '<=', $latestToday)
            ->whereHas('book');

        foreach ($due->lazyById() as $rule) {
            $rules++;

            try {
                $entries += $this->catchUp->handle($rule);
            } catch (Throwable $failure) {
                $failures++;
                Log::error('ledger.recurring_rule_failed', [
                    'rule_id' => $rule->id,
                    'book_id' => $rule->book_id,
                    'exception' => $failure::class,
                ]);
            }
        }

        return new DueEntriesReport($rules, $entries, $failures);
    }
}
