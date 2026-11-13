<?php

declare(strict_types=1);

use Tests\Architecture\Support\ModuleMap;

foreach (ModuleMap::modules() as $module) {
    $actions = ModuleMap::namespaceOf($module).'\\Actions';

    arch("REQ-5.7 {$module} actions are final")
        ->expect($actions)
        ->classes()
        ->toBeFinal();

    arch("REQ-5.7 {$module} actions expose only __construct and handle")
        ->expect($actions)
        ->not->toHavePublicMethodsBesides(['__construct', 'handle']);
}
