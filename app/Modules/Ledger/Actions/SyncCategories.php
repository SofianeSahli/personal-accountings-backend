<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Support\CatalogueEntry;
use App\Modules\Ledger\Support\CategoryCatalogue;
use App\Modules\Ledger\Support\InvalidCatalogue;
use App\Modules\Ledger\Support\SyncReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SyncCategories
{
    /**
     * @throws InvalidCatalogue
     */
    public function handle(CategoryCatalogue $catalogue): SyncReport
    {
        return DB::transaction(function () use ($catalogue): SyncReport {
            $existing = Category::query()->lockForUpdate()->get()->keyBy('code');

            $this->refuseTypeChanges($catalogue, $existing);

            return $this->apply($catalogue, $existing);
        });
    }

    /**
     * @param  Collection<array-key, Category>  $existing
     *
     * @throws InvalidCatalogue
     */
    private function refuseTypeChanges(CategoryCatalogue $catalogue, Collection $existing): void
    {
        $problems = [];

        foreach ($catalogue->entries() as $entry) {
            $current = $existing->get($entry->code);

            if ($current !== null && $current->type !== $entry->type) {
                $problems[] = sprintf(
                    '%s: type cannot change from %s to %s; add a new code instead',
                    $entry->code,
                    $current->type->value,
                    $entry->type->value,
                );
            }
        }

        if ($problems !== []) {
            throw new InvalidCatalogue($problems);
        }
    }

    /**
     * @param  Collection<array-key, Category>  $existing
     */
    private function apply(CategoryCatalogue $catalogue, Collection $existing): SyncReport
    {
        $created = $updated = $restored = $unchanged = 0;
        $idsByCode = $existing->map(fn (Category $category): string => $category->id)->all();

        foreach ($catalogue->parentsFirst() as $entry) {
            $current = $existing->get($entry->code);

            if ($current === null) {
                $idsByCode[$entry->code] = $this->create($entry, $idsByCode)->id;
                $created++;

                continue;
            }

            $wasRetired = $current->isRetired();
            $current->fill(['emoji' => $entry->emoji, 'position' => $entry->position, 'retired_at' => null]);

            if (! $current->isDirty()) {
                $unchanged++;

                continue;
            }

            $current->save();

            if ($wasRetired) {
                $restored++;
            } else {
                $updated++;
            }
        }

        $retired = $this->retireMissing($catalogue, $existing);

        return new SyncReport($created, $updated, $retired, $restored, $unchanged);
    }

    /**
     * @param  array<array-key, string>  $idsByCode
     */
    private function create(CatalogueEntry $entry, array $idsByCode): Category
    {
        return Category::query()->create([
            'parent_id' => $entry->parentCode === null ? null : $idsByCode[$entry->parentCode],
            'code' => $entry->code,
            'translation_key' => Category::translationKeyOf($entry->code),
            'level' => $entry->level,
            'type' => $entry->type,
            'emoji' => $entry->emoji,
            'is_other' => $entry->isOther,
            'position' => $entry->position,
        ]);
    }

    /**
     * @param  Collection<array-key, Category>  $existing
     */
    private function retireMissing(CategoryCatalogue $catalogue, Collection $existing): int
    {
        $codesInFile = array_map(fn (CatalogueEntry $entry): string => $entry->code, $catalogue->entries());

        $missingIds = $existing
            ->reject(fn (Category $category): bool => $category->isRetired() || in_array($category->code, $codesInFile, true))
            ->map(fn (Category $category): string => $category->id)
            ->values()
            ->all();

        if ($missingIds === []) {
            return 0;
        }

        return Category::query()->whereKey($missingIds)->update(['retired_at' => now()]);
    }
}
