<?php

declare(strict_types=1);

namespace App\Modules\Household\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class HouseholdDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly string $householdId) {}
}
