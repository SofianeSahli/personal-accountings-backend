<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Resources;

use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\AccountNames;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TransactionResource extends JsonResource
{
    public function __construct(
        private readonly Transaction $transaction,
        private readonly string $currency,
        private readonly AccountNames $names,
    ) {
        parent::__construct($transaction);
    }

    /**
     * @return array{
     *     id: string,
     *     amount: int,
     *     currency: string,
     *     date: string,
     *     type: string,
     *     category: string,
     *     other_name: ?string,
     *     comment: ?string,
     *     recurring_rule_id: ?string,
     *     created_by: array{id: string, name: ?string},
     *     updated_by: array{id: string, name: ?string},
     *     created_at: string,
     *     updated_at: string,
     * }
     */
    public function toArray(Request $request): array
    {
        $entry = $this->transaction;

        return [
            'id' => $entry->id,
            'amount' => $entry->amount,
            'currency' => $this->currency,
            'date' => $entry->date,
            'type' => $entry->category->type->value,
            'category' => $entry->category->code,
            'other_name' => $entry->other_name,
            'comment' => $entry->comment,
            'recurring_rule_id' => $entry->recurring_rule_id,
            'created_by' => $this->names->account($entry->created_by),
            'updated_by' => $this->names->account($entry->updated_by),
            'created_at' => $entry->created_at->toIso8601ZuluString(),
            'updated_at' => $entry->updated_at->toIso8601ZuluString(),
        ];
    }
}
