<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Tests\Support;

use App\Modules\Ledger\Actions\SyncCategories;
use App\Modules\Ledger\Support\CategoryCatalogue;
use App\Modules\Ledger\Support\InvalidCatalogue;
use App\Modules\Ledger\Support\SyncReport;

final class CategoryScenario
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function catalogue(): array
    {
        return [
            [
                'code' => 'food',
                'type' => 'expense',
                'emoji' => '🛒',
                'children' => [
                    [
                        'code' => 'food.groceries',
                        'emoji' => '🏪',
                        'children' => [
                            ['code' => 'food.groceries.supermarket', 'emoji' => '🥫'],
                            ['code' => 'food.groceries.other', 'emoji' => '📝'],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'income',
                'type' => 'income',
                'emoji' => '💼',
                'children' => [
                    [
                        'code' => 'income.work',
                        'emoji' => '💼',
                        'children' => [
                            ['code' => 'income.work.salary', 'emoji' => null],
                            ['code' => 'income.work.other', 'emoji' => '📝'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function problemsOf(mixed $nodes): array
    {
        try {
            CategoryCatalogue::fromArray($nodes);
        } catch (InvalidCatalogue $invalid) {
            return $invalid->problems;
        }

        return [];
    }

    public static function sync(mixed $nodes): SyncReport
    {
        return app(SyncCategories::class)->handle(CategoryCatalogue::fromArray($nodes));
    }

    public static function writeFile(string $contents): string
    {
        $path = sys_get_temp_dir().'/categories_'.bin2hex(random_bytes(8)).'.php';
        file_put_contents($path, $contents);

        return $path;
    }

    public static function fileOf(mixed $nodes): string
    {
        return self::writeFile("<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($nodes, true).";\n");
    }
}
