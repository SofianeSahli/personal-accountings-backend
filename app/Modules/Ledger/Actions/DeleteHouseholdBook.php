<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Book;

final class DeleteHouseholdBook
{
    public function handle(string $householdId): void
    {
        Book::query()
            ->where('owner_type', Book::HOUSEHOLD_OWNER)
            ->where('owner_id', $householdId)
            ->delete();
    }
}
