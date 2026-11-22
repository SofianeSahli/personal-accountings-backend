<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class QrTokenRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['bail', 'required', 'string'],
        ];
    }

    public function token(): string
    {
        return (string) $this->validated('token');
    }
}
