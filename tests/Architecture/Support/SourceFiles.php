<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SourceFiles
{
    public static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @param  list<string>  $paths  directories or files, relative to the project root
     * @param  list<string>  $excluded  path prefixes, relative to the project root
     * @return array<string, string> relative path => contents
     */
    public static function read(array $paths, string $extensionPattern = '/\.php$/', array $excluded = []): array
    {
        $files = [];

        foreach ($paths as $path) {
            foreach (self::filesUnder(self::root().'/'.$path) as $absolute) {
                $relative = self::relative($absolute);

                if (preg_match($extensionPattern, $relative) !== 1 || self::isExcluded($relative, $excluded)) {
                    continue;
                }

                $files[$relative] = (string) file_get_contents($absolute);
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    public static function topLevelDirectories(string $path): array
    {
        $directories = glob(self::root().'/'.$path.'/*', GLOB_ONLYDIR) ?: [];

        return array_map('basename', $directories);
    }

    /**
     * @return list<string>
     */
    private static function filesUnder(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }

        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private static function relative(string $absolute): string
    {
        return ltrim(str_replace('\\', '/', substr($absolute, strlen(self::root()))), '/');
    }

    /**
     * @param  list<string>  $excluded
     */
    private static function isExcluded(string $relative, array $excluded): bool
    {
        foreach ($excluded as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
