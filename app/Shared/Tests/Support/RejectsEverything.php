<?php

declare(strict_types=1);

namespace App\Shared\Tests\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class RejectsEverything implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $fail('A translated message that must never reach the response.');
    }
}
