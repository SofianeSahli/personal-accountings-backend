<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GoogleSignInRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'device_id' => IdentityRules::deviceId($this),
            'terms_version' => ['nullable', ...IdentityRules::currentTerms()],
            'phone' => IdentityRules::phone(),
            'locale' => IdentityRules::locale(),
        ];
    }
}
