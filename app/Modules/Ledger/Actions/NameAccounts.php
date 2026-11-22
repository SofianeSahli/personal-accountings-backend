<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Identity\Actions\Accounts\FindAccountNames;
use App\Modules\Ledger\Support\AccountNames;

final class NameAccounts
{
    public function __construct(private readonly FindAccountNames $findAccountNames) {}

    /**
     * @param  iterable<object{created_by: string, updated_by: string}>  $records
     */
    public function handle(iterable $records): AccountNames
    {
        $ids = [];

        foreach ($records as $record) {
            $ids[$record->created_by] = true;
            $ids[$record->updated_by] = true;
        }

        return new AccountNames($this->findAccountNames->handle(array_map('strval', array_keys($ids))));
    }
}
