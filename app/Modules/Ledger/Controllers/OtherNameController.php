<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers;

use App\Modules\Ledger\Actions\SuggestOtherNames;
use App\Modules\Ledger\Controllers\Requests\SuggestOtherNamesRequest;
use App\Modules\Ledger\Controllers\Resources\OtherNameList;
use App\Modules\Ledger\Support\CurrentBook;

final class OtherNameController
{
    public function index(SuggestOtherNamesRequest $request, CurrentBook $current, SuggestOtherNames $suggestOtherNames): OtherNameList
    {
        return new OtherNameList($suggestOtherNames->handle($current->book, $request->categoryCode()));
    }
}
