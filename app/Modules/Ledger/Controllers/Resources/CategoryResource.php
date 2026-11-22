<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Resources;

use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\CategoryLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CategoryResource extends JsonResource
{
    public function __construct(
        private readonly Category $category,
        private readonly bool $parentRetired = false,
    ) {
        parent::__construct($category);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $retired = $this->parentRetired || $this->category->isRetired();

        $node = [
            'code' => $this->category->code,
            'translation_key' => $this->category->translation_key,
            'emoji' => $this->category->emoji,
        ];

        return match ($this->category->level) {
            CategoryLevel::Top => [
                ...$node,
                'type' => $this->category->type->value,
                'retired' => $retired,
                'groups' => $this->children($request, $retired),
            ],
            CategoryLevel::Group => [
                ...$node,
                'retired' => $retired,
                'leaves' => $this->children($request, $retired),
            ],
            CategoryLevel::Leaf => [
                ...$node,
                'retired' => $retired,
                'is_other' => $this->category->is_other,
            ],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function children(Request $request, bool $retired): array
    {
        return $this->category->children
            ->map(fn (Category $child): array => (new self($child, $retired))->toArray($request))
            ->values()
            ->all();
    }
}
