<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Events\HouseholdDeleted;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Household\Models\InvitationStatus;
use Illuminate\Support\Facades\DB;

final class DeleteHousehold
{
    public function handle(Household $household): void
    {
        DB::transaction(function () use ($household): void {
            $locked = Household::lockLive($household->id);

            if ($locked === null) {
                return;
            }

            HouseholdMember::query()->where('household_id', $locked->id)->delete();
            $this->cancelPendingInvitations($locked);
            $locked->delete();

            HouseholdDeleted::dispatch($locked->id);
        });
    }

    private function cancelPendingInvitations(Household $household): void
    {
        $cancelled = ['status' => InvitationStatus::Cancelled->value, 'closed_at' => now()];

        HouseholdInvitation::query()
            ->where('household_id', $household->id)
            ->where('status', InvitationStatus::Pending->value)
            ->update($cancelled);
        HouseholdQrInvitation::query()
            ->where('household_id', $household->id)
            ->where('status', InvitationStatus::Pending->value)
            ->update($cancelled);
    }
}
