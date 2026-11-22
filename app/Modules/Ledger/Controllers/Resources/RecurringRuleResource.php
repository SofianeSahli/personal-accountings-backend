<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Resources;

use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Support\AccountNames;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RecurringRuleResource extends JsonResource
{
    public function __construct(
        private readonly RecurringRule $rule,
        private readonly string $currency,
        private readonly AccountNames $names,
    ) {
        parent::__construct($rule);
    }

    /**
     * @return array{
     *     id: string,
     *     amount: int,
     *     currency: string,
     *     type: string,
     *     category: string,
     *     other_name: ?string,
     *     comment: ?string,
     *     frequency: string,
     *     interval: int,
     *     starts_on: string,
     *     ends_on: ?string,
     *     next_date: ?string,
     *     created_by: array{id: string, name: ?string},
     *     updated_by: array{id: string, name: ?string},
     *     created_at: string,
     *     updated_at: string,
     * }
     */
    public function toArray(Request $request): array
    {
        $rule = $this->rule;

        return [
            'id' => $rule->id,
            'amount' => $rule->amount,
            'currency' => $this->currency,
            'type' => $rule->category->type->value,
            'category' => $rule->category->code,
            'other_name' => $rule->other_name,
            'comment' => $rule->comment,
            'frequency' => $rule->frequency->value,
            'interval' => $rule->interval,
            'starts_on' => $rule->starts_on,
            'ends_on' => $rule->ends_on,
            'next_date' => $rule->next_due_on,
            'created_by' => $this->names->account($rule->created_by),
            'updated_by' => $this->names->account($rule->updated_by),
            'created_at' => $rule->created_at->toIso8601ZuluString(),
            'updated_at' => $rule->updated_at->toIso8601ZuluString(),
        ];
    }
}
