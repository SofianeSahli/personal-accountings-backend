<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Resources;

use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\TransactionPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class TransactionList extends ResourceCollection
{
    public function __construct(
        private readonly TransactionPage $transactions,
        private readonly string $currency,
    ) {
        parent::__construct($transactions->page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return array_values(array_map(
            fn (Transaction $transaction): array => (new TransactionResource(
                $transaction,
                $this->currency,
                $this->transactions->names,
            ))->toArray($request),
            $this->transactions->page->items(),
        ));
    }
}
