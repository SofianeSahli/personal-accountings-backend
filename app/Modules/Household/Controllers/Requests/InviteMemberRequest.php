<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Requests;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Support\CurrentHousehold;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class InviteMemberRequest extends FormRequest
{
    public function authorize(CurrentHousehold $current): Response
    {
        return Gate::inspect('manageMembers', [Household::class, $current->household]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => InvitationRules::email(),
        ];
    }

    public function invitedEmail(): string
    {
        return (string) $this->validated('email');
    }

    protected function prepareForValidation(): void
    {
        InvitationRules::prepareEmail($this);
    }
}
