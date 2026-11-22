<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\CategoryLevel;
use App\Modules\Ledger\Support\CategoryChoice;
use App\Modules\Ledger\Support\ChosenCategory;
use App\Modules\Ledger\Support\FieldError;

final class ChooseCategory
{
    private const CATEGORY = 'category';

    private const OTHER_NAME = 'other_name';

    public function handle(CategoryChoice $choice): ChosenCategory
    {
        $category = $choice->code === null || $choice->code === $choice->current?->code
            ? $choice->current
            : $this->newLeaf($choice->code);

        if ($category === null) {
            throw FieldError::of(self::CATEGORY, 'category.required');
        }

        if (! $category->is_other) {
            if ($choice->sendsOtherName && $choice->otherName !== null) {
                throw FieldError::of(self::OTHER_NAME, 'other_name.prohibited');
            }

            return new ChosenCategory($category, null);
        }

        $otherName = match (true) {
            $choice->sendsOtherName => $choice->otherName,
            $category->id === $choice->current?->id => $choice->currentOtherName,
            default => null,
        };

        if ($otherName === null) {
            throw FieldError::of(self::OTHER_NAME, 'other_name.required');
        }

        return new ChosenCategory($category, $otherName);
    }

    private function newLeaf(string $code): Category
    {
        $category = Category::query()->where('code', $code)->first();

        return match (true) {
            $category === null => throw FieldError::of(self::CATEGORY, 'category.not_found'),
            $category->level !== CategoryLevel::Leaf => throw FieldError::of(self::CATEGORY, 'category.not_leaf'),
            $category->isRetired() => throw FieldError::of(self::CATEGORY, 'category.retired'),
            default => $category,
        };
    }
}
