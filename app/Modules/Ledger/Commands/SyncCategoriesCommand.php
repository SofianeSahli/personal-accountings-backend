<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Commands;

use App\Modules\Ledger\Actions\SyncCategories;
use App\Modules\Ledger\Support\CategoryCatalogue;
use App\Modules\Ledger\Support\InvalidCatalogue;
use Illuminate\Console\Command;

final class SyncCategoriesCommand extends Command
{
    public const SHIPPED_CATALOGUE = __DIR__.'/../resources/categories.php';

    protected $signature = 'ledger:sync-categories
        {--file= : Catalogue file to read instead of the shipped one}';

    protected $description = 'Bring the categories table in line with the category catalogue file';

    public function handle(SyncCategories $syncCategories): int
    {
        try {
            $report = $syncCategories->handle(CategoryCatalogue::fromArray($this->readCatalogue()));
        } catch (InvalidCatalogue $invalid) {
            $this->error('The category catalogue is invalid. Nothing was changed.');

            foreach ($invalid->problems as $problem) {
                $this->line('  - '.$problem);
            }

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Categories: %d created, %d updated, %d retired, %d restored, %d unchanged.',
            $report->created,
            $report->updated,
            $report->retired,
            $report->restored,
            $report->unchanged,
        ));

        return self::SUCCESS;
    }

    /**
     * @throws InvalidCatalogue
     */
    private function readCatalogue(): mixed
    {
        $option = $this->option('file');
        $path = is_string($option) && $option !== '' ? $option : self::SHIPPED_CATALOGUE;

        if (! is_file($path)) {
            throw new InvalidCatalogue([sprintf('%s: file not found', $path)]);
        }

        $nodes = require $path;

        if (! is_array($nodes)) {
            throw new InvalidCatalogue([sprintf('%s: must return an array of categories', $path)]);
        }

        return $nodes;
    }
}
