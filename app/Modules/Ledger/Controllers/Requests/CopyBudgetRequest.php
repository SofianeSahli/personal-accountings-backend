<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CopyBudgetRequest extends FormRequest
{
    use ValidatesBudgetMonth;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'replace' => ['nullable', 'boolean:strict'],
        ];
    }

    public function replaces(): bool
    {
        return $this->validated('replace') === true;
    }
}
