<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Commands;

use App\Modules\Ledger\Actions\RecordDueEntries;
use Illuminate\Console\Command;

final class RecordDueEntriesCommand extends Command
{
    protected $signature = 'ledger:record-due-entries';

    protected $description = 'Record the entries of every recurring rule that are due in their book\'s timezone';

    public function handle(RecordDueEntries $recordDueEntries): int
    {
        $report = $recordDueEntries->handle();

        $this->info(sprintf(
            'Recurring rules: %d checked, %d entries recorded, %d failed.',
            $report->rules,
            $report->entries,
            $report->failures,
        ));

        return $report->failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
