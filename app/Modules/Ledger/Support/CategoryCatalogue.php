<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\CategoryLevel;
use App\Modules\Ledger\Models\CategoryType;

final class CategoryCatalogue
{
    public const OTHER_SEGMENT = 'other';

    private const SEGMENT_PATTERN = '/^[a-z][a-z0-9_]*$/';

    private const RESERVED_SEGMENT = 'label';

    private const CODE_KEY = 'code';

    private const EMOJI_KEY = 'emoji';

    private const TYPE_KEY = 'type';

    private const CHILDREN_KEY = 'children';

    /** @var list<CatalogueEntry> */
    private array $entries = [];

    /** @var list<string> */
    private array $problems = [];

    /** @var array<string, true> */
    private array $seenCodes = [];

    private function __construct() {}

    /**
     * @throws InvalidCatalogue
     */
    public static function fromArray(mixed $nodes): self
    {
        $catalogue = new self;
        $catalogue->readChildren($nodes, null, null, 'catalogue');

        if ($catalogue->problems !== []) {
            throw new InvalidCatalogue($catalogue->problems);
        }

        return $catalogue;
    }

    /**
     * @return list<CatalogueEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<CatalogueEntry>
     */
    public function parentsFirst(): array
    {
        $entries = $this->entries;
        usort($entries, fn (CatalogueEntry $a, CatalogueEntry $b): int => $a->level->value <=> $b->level->value);

        return $entries;
    }

    private function readChildren(mixed $nodes, ?string $parentCode, ?CategoryType $type, string $where): void
    {
        if (! is_array($nodes) || ! array_is_list($nodes) || $nodes === []) {
            $this->problems[] = sprintf('%s: needs a non-empty list of categories', $where);

            return;
        }

        $codesByPosition = [];

        foreach ($nodes as $position => $node) {
            $code = $this->readNode($node, $parentCode, $type, $position, $where);

            if ($code !== null) {
                $codesByPosition[$position] = $code;
            }
        }

        if ($parentCode !== null && $this->levelOf($parentCode) === CategoryLevel::Group) {
            $this->checkOtherLeafIsLast($parentCode, $codesByPosition, count($nodes));
        }
    }

    private function readNode(mixed $node, ?string $parentCode, ?CategoryType $type, int $position, string $where): ?string
    {
        $code = is_array($node) ? ($node[self::CODE_KEY] ?? null) : null;

        if (! is_array($node) || ! is_string($code)) {
            $this->problems[] = sprintf('%s, item %d: needs a string code', $where, $position);

            return null;
        }

        if (! $this->checkCode($code, $parentCode)) {
            return null;
        }

        $level = $this->levelOf($code);
        $this->checkKeys($code, $node, $level);
        $type = $level === CategoryLevel::Top ? $this->readType($code, $node) : $type;
        $emoji = $this->readEmoji($code, $node, $level);

        if ($type !== null) {
            $this->entries[] = new CatalogueEntry(
                code: $code,
                parentCode: $parentCode,
                level: $level,
                type: $type,
                emoji: $emoji,
                position: $position,
                isOther: $level === CategoryLevel::Leaf && $this->lastSegmentOf($code) === self::OTHER_SEGMENT,
            );
        }

        if ($level !== CategoryLevel::Leaf) {
            $this->readChildren($node[self::CHILDREN_KEY] ?? null, $code, $type, $code);
        }

        return $code;
    }

    private function checkCode(string $code, ?string $parentCode): bool
    {
        $segments = explode('.', $code);
        $valid = true;

        foreach ($segments as $segment) {
            if (preg_match(self::SEGMENT_PATTERN, $segment) !== 1 || $segment === self::RESERVED_SEGMENT) {
                $this->problems[] = sprintf('%s: segment "%s" must match %s and must not be "%s"', $code, $segment, self::SEGMENT_PATTERN, self::RESERVED_SEGMENT);
                $valid = false;
            }
        }

        $expectedDepth = $parentCode === null ? 1 : count(explode('.', $parentCode)) + 1;

        if (count($segments) !== $expectedDepth) {
            $this->problems[] = sprintf('%s: must have %d segment(s) to sit at level %d of 3 (top, group, leaf)', $code, $expectedDepth, $expectedDepth);
            $valid = false;
        } elseif ($parentCode !== null && ! str_starts_with($code, $parentCode.'.')) {
            $this->problems[] = sprintf('%s: must start with its parent\'s code "%s."', $code, $parentCode);
            $valid = false;
        }

        if (isset($this->seenCodes[$code])) {
            $this->problems[] = sprintf('%s: appears more than once', $code);
            $valid = false;
        }

        $this->seenCodes[$code] = true;

        return $valid;
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private function checkKeys(string $code, array $node, CategoryLevel $level): void
    {
        $allowed = match ($level) {
            CategoryLevel::Top => [self::CODE_KEY, self::EMOJI_KEY, self::TYPE_KEY, self::CHILDREN_KEY],
            CategoryLevel::Group => [self::CODE_KEY, self::EMOJI_KEY, self::CHILDREN_KEY],
            CategoryLevel::Leaf => [self::CODE_KEY, self::EMOJI_KEY],
        };

        foreach (array_diff(array_keys($node), $allowed) as $key) {
            $this->problems[] = sprintf('%s: a %s category cannot have "%s"', $code, strtolower($level->name), $key);
        }
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private function readType(string $code, array $node): ?CategoryType
    {
        $value = $node[self::TYPE_KEY] ?? null;
        $type = is_string($value) ? CategoryType::tryFrom($value) : null;

        if ($type === null) {
            $this->problems[] = sprintf('%s: type must be expense, income or saving', $code);
        }

        return $type;
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private function readEmoji(string $code, array $node, CategoryLevel $level): ?string
    {
        $emoji = $node[self::EMOJI_KEY] ?? null;

        if (is_string($emoji) && $emoji !== '') {
            return $emoji;
        }

        if ($level !== CategoryLevel::Leaf) {
            $this->problems[] = sprintf('%s: needs an emoji', $code);
        } elseif ($emoji !== null) {
            $this->problems[] = sprintf('%s: emoji must be a non-empty string or null', $code);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $codesByPosition
     */
    private function checkOtherLeafIsLast(string $groupCode, array $codesByPosition, int $leafCount): void
    {
        $otherPosition = array_search($groupCode.'.'.self::OTHER_SEGMENT, $codesByPosition, true);

        if ($otherPosition === false) {
            $this->problems[] = sprintf('%s: needs an "%s" leaf', $groupCode, self::OTHER_SEGMENT);
        } elseif ($otherPosition !== $leafCount - 1) {
            $this->problems[] = sprintf('%s: its "%s" leaf must be the last one', $groupCode, self::OTHER_SEGMENT);
        }
    }

    private function levelOf(string $code): CategoryLevel
    {
        return CategoryLevel::from(substr_count($code, '.') + 1);
    }

    private function lastSegmentOf(string $code): string
    {
        $segments = explode('.', $code);

        return $segments[array_key_last($segments)];
    }
}
