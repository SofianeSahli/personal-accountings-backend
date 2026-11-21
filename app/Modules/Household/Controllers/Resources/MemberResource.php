<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Resources;

use App\Modules\Household\Models\HouseholdMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MemberResource extends JsonResource
{
    public function __construct(
        private readonly HouseholdMember $member,
        private readonly string $name,
    ) {
        parent::__construct($member);
    }

    /**
     * @return array{id: string, name: string, role: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->member->user_id,
            'name' => $this->name,
            'role' => $this->member->role->value,
        ];
    }
}
