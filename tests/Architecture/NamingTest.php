<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Tests\Architecture\Support\AppClasses;

const FORBIDDEN_SUFFIXES = ['Repository', 'Service'];
const FORBIDDEN_PREFIX = 'Base';

function isForbiddenName(string $shortName): bool
{
    foreach (FORBIDDEN_SUFFIXES as $suffix) {
        if (str_ends_with($shortName, $suffix)) {
            return true;
        }
    }

    return str_starts_with($shortName, FORBIDDEN_PREFIX);
}

arch('REQ-5.6 no class is named *Repository')->expect('App')->classes()->not->toHaveSuffix('Repository');

arch('REQ-5.6 no class is named *Service')->expect('App')->classes()->not->toHaveSuffix('Service');

arch('REQ-5.6 no class is named Base*')->expect('App')->classes()->not->toHavePrefix('Base');

it('REQ-5.6 no class extends a *Repository, *Service or Base* class, Laravel service providers aside', function (): void {
    $violations = [];

    foreach (AppClasses::all() as $class) {
        for ($parent = $class->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
            if ($parent->getName() === ServiceProvider::class) {
                break;
            }

            if (isForbiddenName($parent->getShortName())) {
                $violations[] = "{$class->getName()} extends {$parent->getName()}";
            }
        }
    }

    expect($violations)->toBe([]);
});
