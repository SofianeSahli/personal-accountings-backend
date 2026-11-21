<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Resources;

use App\Modules\Ledger\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BookResource extends JsonResource
{
    public function __construct(
        private readonly Book $book,
        private readonly bool $canManage,
    ) {
        parent::__construct($book);
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     currency: array{code: string, exponent: int},
     *     timezone: string,
     *     owner: array{type: string, id: string},
     *     can_manage: bool,
     * }
     */
    public function toArray(Request $request): array
    {
        $currency = $this->book->currencyUnit();

        return [
            'id' => $this->book->id,
            'name' => $this->book->name,
            'currency' => ['code' => $currency->code, 'exponent' => $currency->exponent],
            'timezone' => $this->book->timezone,
            'owner' => ['type' => $this->book->owner_type, 'id' => $this->book->owner_id],
            'can_manage' => $this->canManage,
        ];
    }
}
