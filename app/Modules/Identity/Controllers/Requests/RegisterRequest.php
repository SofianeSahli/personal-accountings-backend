<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => IdentityRules::name(),
            'email' => IdentityRules::email(),
            'password' => IdentityRules::newPassword(),
            'terms_version' => ['required', ...IdentityRules::currentTerms()],
            'phone' => IdentityRules::phone(),
            'locale' => IdentityRules::locale(),
        ];
    }

    protected function prepareForValidation(): void
    {
        IdentityRules::prepareEmail($this);
    }
}
