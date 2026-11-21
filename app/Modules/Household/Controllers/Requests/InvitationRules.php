<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InvitationRules
{
    public const EMAIL_MAX_LENGTH = 255;

    public static function prepareEmail(FormRequest $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    /**
     * @return list<string>
     */
    public static function email(): array
    {
        return ['bail', 'required', 'string', 'email', 'max:'.self::EMAIL_MAX_LENGTH];
    }
}
