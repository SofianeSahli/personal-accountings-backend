<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

final class ModuleMap
{
    public const MODULES_NAMESPACE = 'App\\Modules';

    public const SHARED_USER_MODEL = 'App\\Modules\\Identity\\Models\\User';

    /** @var array<string, list<string>> */
    public const ALLOWED = [
        'Identity' => [],
        'Household' => ['Identity'],
        'Ledger' => ['Household', 'Identity'],
    ];

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        $directories = glob(SourceFiles::root().'/app/Modules/*', GLOB_ONLYDIR) ?: [];

        return array_values(array_unique([
            ...array_keys(self::ALLOWED),
            ...array_map('basename', $directories),
        ]));
    }

    /**
     * @return list<string>
     */
    public static function forbiddenNamespacesFor(string $module): array
    {
        $allowed = [$module, ...(self::ALLOWED[$module] ?? [])];

        return array_values(array_map(
            fn (string $other): string => self::namespaceOf($other),
            array_diff(self::modules(), $allowed),
        ));
    }

    public static function namespaceOf(string $module): string
    {
        return self::MODULES_NAMESPACE.'\\'.$module;
    }
}
