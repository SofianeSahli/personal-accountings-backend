<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EmailAddressRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => IdentityRules::email(),
        ];
    }

    protected function prepareForValidation(): void
    {
        IdentityRules::prepareEmail($this);
    }
}
