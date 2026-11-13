<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

use ReflectionClass;

final class AppClasses
{
    private const NAMESPACE_PATTERN = '/^namespace\s+(App[\w\\\\]*)\s*;/m';

    private const DECLARATION_PATTERN = '/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m';

    /**
     * @return list<ReflectionClass<object>>
     */
    public static function all(): array
    {
        $classes = [];

        foreach (SourceFiles::read(['app']) as $contents) {
            if (preg_match(self::NAMESPACE_PATTERN, $contents, $namespace) !== 1
                || preg_match(self::DECLARATION_PATTERN, $contents, $declaration) !== 1) {
                continue;
            }

            $name = $namespace[1].'\\'.$declaration[1];

            if (class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name)) {
                $classes[] = new ReflectionClass($name);
            }
        }

        return $classes;
    }
}
