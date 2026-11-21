<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Resources;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HouseholdResource extends JsonResource
{
    public function __construct(
        private readonly Household $household,
        private readonly HouseholdRole $role,
    ) {
        parent::__construct($household);
    }

    /**
     * @return array{id: string, name: string, role: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->household->id,
            'name' => $this->household->name,
            'role' => $this->role->value,
        ];
    }
}
