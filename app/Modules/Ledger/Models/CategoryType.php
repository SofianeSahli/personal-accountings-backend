<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

enum CategoryType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Saving = 'saving';
}
