<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Listeners;

use App\Modules\Household\Events\HouseholdDeleted;
use App\Modules\Ledger\Actions\DeleteHouseholdBook;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SoftDeleteHouseholdBook
{
    public function __construct(private readonly DeleteHouseholdBook $deleteHouseholdBook) {}

    public function handle(HouseholdDeleted $event): void
    {
        try {
            $this->deleteHouseholdBook->handle($event->householdId);
        } catch (Throwable) {
            Log::error('ledger.household_book_delete_failed', ['household_id' => $event->householdId]);
        }
    }
}
