<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers\Requests;

use App\Modules\Identity\Models\Locale;
use App\Modules\Identity\Rules\BcryptLimit;
use App\Modules\Identity\Rules\InternationalPhone;
use App\Modules\Identity\Support\SignInMode;
use App\Modules\Identity\Support\SignInTarget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\RequiredIf;

final class IdentityRules
{
    private const PASSWORD_MIN_LENGTH = 8;

    private const NAME_MAX_LENGTH = 120;

    private const EMAIL_MAX_LENGTH = 255;

    private const CODE_DIGITS = 6;

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
    public static function name(): array
    {
        return ['required', 'string', 'max:'.self::NAME_MAX_LENGTH];
    }

    /**
     * @return list<string>
     */
    public static function email(): array
    {
        return ['required', 'string', 'email', 'max:'.self::EMAIL_MAX_LENGTH];
    }

    /**
     * @return list<string|ValidationRule>
     */
    public static function newPassword(): array
    {
        return ['required', 'string', 'min:'.self::PASSWORD_MIN_LENGTH, new BcryptLimit];
    }

    /**
     * @return list<string>
     */
    public static function code(): array
    {
        return ['required', 'string', 'digits:'.self::CODE_DIGITS];
    }

    /**
     * @return list<string>
     */
    public static function pendingSignIn(): array
    {
        return ['required', 'string'];
    }

    /**
     * @return list<string|ValidationRule>
     */
    public static function phone(): array
    {
        return ['nullable', new InternationalPhone];
    }

    /**
     * @return list<string|Enum>
     */
    public static function locale(): array
    {
        return ['nullable', Rule::enum(Locale::class)];
    }

    /**
     * @return list<string|In>
     */
    public static function currentTerms(): array
    {
        return ['string', Rule::in([config('identity.terms_version')])];
    }

    /**
     * @return list<string|RequiredIf>
     */
    public static function deviceId(FormRequest $request): array
    {
        return [
            Rule::requiredIf(fn (): bool => SignInMode::of($request) === SignInMode::Device),
            'string',
            'uuid',
        ];
    }

    public static function signInTarget(FormRequest $request): SignInTarget
    {
        $deviceId = $request->validated('device_id');

        if (SignInMode::of($request) === SignInMode::Web || ! is_string($deviceId)) {
            return SignInTarget::web();
        }

        return SignInTarget::device($deviceId);
    }

    public static function normalizedPhone(FormRequest $request): ?string
    {
        $phone = $request->validated('phone');

        return is_string($phone) ? InternationalPhone::normalize($phone) : null;
    }

    public static function chosenLocale(FormRequest $request): Locale
    {
        $locale = $request->validated('locale');

        return is_string($locale) ? Locale::from($locale) : Locale::Fr;
    }
}
