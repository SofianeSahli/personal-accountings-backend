<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Resources;

use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Support\RecurringRulePage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class RecurringRuleList extends ResourceCollection
{
    public function __construct(
        private readonly RecurringRulePage $rules,
        private readonly string $currency,
    ) {
        parent::__construct($rules->page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return array_values(array_map(
            fn (RecurringRule $rule): array => (new RecurringRuleResource(
                $rule,
                $this->currency,
                $this->rules->names,
            ))->toArray($request),
            $this->rules->page->items(),
        ));
    }
}
