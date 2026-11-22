<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use RuntimeException;

final class InvalidCatalogue extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('category_catalogue.invalid');
    }
}
