<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers;

use App\Modules\Ledger\Actions\SummarizePeriod;
use App\Modules\Ledger\Controllers\Requests\SummaryRequest;
use App\Modules\Ledger\Controllers\Resources\SummaryResource;
use App\Modules\Ledger\Support\CurrentBook;

final class SummaryController
{
    public function show(SummaryRequest $request, CurrentBook $current, SummarizePeriod $summarizePeriod): SummaryResource
    {
        return new SummaryResource($summarizePeriod->handle($current->book, $request->period($current->book)));
    }
}
