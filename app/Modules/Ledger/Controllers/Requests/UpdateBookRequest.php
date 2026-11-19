<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Support\BookChanges;
use App\Modules\Ledger\Support\CurrentBook;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateBookRequest extends FormRequest
{
    public function authorize(CurrentBook $current): Response
    {
        return Gate::inspect('update', [Book::class, $current->book]);
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', ...BookRules::name()],
            'timezone' => ['sometimes', 'required', ...BookRules::timezone()],
            'currency' => ['prohibited'],
            'household_id' => ['prohibited'],
        ];
    }

    public function changes(): BookChanges
    {
        $name = $this->validated('name');
        $timezone = $this->validated('timezone');

        return new BookChanges(
            name: is_string($name) ? $name : null,
            timezone: is_string($timezone) ? $timezone : null,
        );
    }
}
