<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\Household;

final class RenameHousehold
{
    public function handle(Household $household, ?string $name): Household
    {
        if ($name !== null) {
            $household->update(['name' => $name]);
        }

        return $household;
    }
}
