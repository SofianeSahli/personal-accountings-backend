<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Rules;

use App\Modules\Ledger\Support\BookDay;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class WithinOneYear implements DataAwareRule, ValidationRule
{
    /**
     * @var array<array-key, mixed>
     */
    private array $data = [];

    public function __construct(private readonly string $startField) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $start = $this->data[$this->startField] ?? null;

        if (! is_string($start) || ! is_string($value)) {
            return;
        }

        try {
            $limit = BookDay::addYears($start, 1);
        } catch (InvalidArgumentException) {
            return;
        }

        if ($value >= $limit) {
            $fail($attribute.'.within_one_year');
        }
    }
}
