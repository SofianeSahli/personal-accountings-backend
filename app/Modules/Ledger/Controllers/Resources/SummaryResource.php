<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Resources;

use App\Modules\Ledger\Support\CategoryTotal;
use App\Modules\Ledger\Support\MemberTotal;
use App\Modules\Ledger\Support\OtherNameTotal;
use App\Modules\Ledger\Support\Summary;
use App\Modules\Ledger\Support\TypeTotals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SummaryResource extends JsonResource
{
    public function __construct(private readonly Summary $summary)
    {
        parent::__construct($summary);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $days = [];

        foreach ($this->summary->days as $day => $totals) {
            $days[] = ['date' => (string) $day, ...$this->totals($totals)];
        }

        $months = [];

        foreach ($this->summary->months as $month => $totals) {
            $months[] = ['month' => (string) $month, ...$this->totals($totals)];
        }

        return [
            'from' => $this->summary->period->from,
            'to' => $this->summary->period->to,
            'currency' => $this->summary->currency,
            'totals' => $this->totals($this->summary->totals),
            'categories' => array_map($this->category(...), $this->summary->categories),
            'members' => array_map($this->member(...), $this->summary->members),
            'days' => $days,
            'months' => $months,
        ];
    }

    /**
     * @return array{income: int, expense: int, saving: int, balance: int, entries: int}
     */
    private function totals(TypeTotals $totals): array
    {
        return [
            'income' => $totals->income,
            'expense' => $totals->expense,
            'saving' => $totals->saving,
            'balance' => $totals->balance(),
            'entries' => $totals->entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function category(CategoryTotal $category): array
    {
        return [
            'category' => $category->code,
            'type' => $category->type->value,
            'total' => $category->total,
            'entries' => $category->entries,
            'children' => array_map($this->category(...), $category->children),
            'other_names' => array_map(
                fn (OtherNameTotal $name): array => ['name' => $name->name, 'total' => $name->total, 'entries' => $name->entries],
                $category->otherNames,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function member(MemberTotal $member): array
    {
        return [
            'account' => ['id' => $member->accountId, 'name' => $member->name],
            ...$this->totals($member->totals),
        ];
    }
}
