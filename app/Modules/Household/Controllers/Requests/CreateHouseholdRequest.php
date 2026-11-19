<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateHouseholdRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => HouseholdRules::name(),
        ];
    }

    public function householdName(): string
    {
        return (string) $this->validated('name');
    }
}
