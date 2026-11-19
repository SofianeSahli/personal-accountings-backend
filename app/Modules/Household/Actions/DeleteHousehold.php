<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Events\HouseholdDeleted;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use Illuminate\Support\Facades\DB;

final class DeleteHousehold
{
    public function handle(Household $household): void
    {
        DB::transaction(function () use ($household): void {
            HouseholdMember::query()->where('household_id', $household->id)->delete();
            $household->delete();

            HouseholdDeleted::dispatch($household->id);
        });
    }
}
