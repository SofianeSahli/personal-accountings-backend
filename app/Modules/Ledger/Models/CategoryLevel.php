<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

enum CategoryLevel: int
{
    case Top = 1;
    case Group = 2;
    case Leaf = 3;
}
