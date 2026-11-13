<?php

declare(strict_types=1);

use Tests\Architecture\Support\ModuleMap;

arch('REQ-5.3 Shared uses no module')
    ->expect('App\Shared')
    ->not->toUse(ModuleMap::MODULES_NAMESPACE);

foreach (ModuleMap::modules() as $module) {
    $forbidden = ModuleMap::forbiddenNamespacesFor($module);

    if ($forbidden === []) {
        continue;
    }

    arch("REQ-5.4 {$module} uses only the modules allowed for it")
        ->expect(ModuleMap::namespaceOf($module))
        ->not->toUse($forbidden);
}
