<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SuggestOtherNamesRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string'],
        ];
    }

    public function categoryCode(): string
    {
        return (string) $this->validated('category');
    }
}
