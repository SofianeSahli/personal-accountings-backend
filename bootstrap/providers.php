<?php

declare(strict_types=1);

use App\Modules\Household\HouseholdServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Ledger\LedgerServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    HouseholdServiceProvider::class,
    LedgerServiceProvider::class,
];
