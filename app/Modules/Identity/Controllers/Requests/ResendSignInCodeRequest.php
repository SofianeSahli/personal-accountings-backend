<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ResendSignInCodeRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'pending_sign_in' => IdentityRules::pendingSignIn(),
        ];
    }
}
