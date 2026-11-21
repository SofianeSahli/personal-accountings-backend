<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Requests;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Support\CurrentHousehold;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateHouseholdRequest extends FormRequest
{
    public function authorize(CurrentHousehold $current): Response
    {
        return Gate::inspect('update', [Household::class, $current->household]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', ...HouseholdRules::name()],
        ];
    }

    public function householdName(): ?string
    {
        $name = $this->validated('name');

        return is_string($name) ? $name : null;
    }
}
