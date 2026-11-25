<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ShowBudgetRequest extends FormRequest
{
    use ValidatesBudgetMonth;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
