<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

enum Frequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
