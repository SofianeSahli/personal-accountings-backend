<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\CategoryChoice;
use App\Modules\Ledger\Support\TransactionChanges;
use App\Modules\Ledger\Support\TransactionView;

final class UpdateTransaction
{
    public function __construct(
        private readonly ChooseCategory $chooseCategory,
        private readonly NameAccounts $nameAccounts,
    ) {}

    public function handle(Transaction $transaction, User $user, TransactionChanges $changes): TransactionView
    {
        $chosen = $this->chooseCategory->handle(new CategoryChoice(
            code: $changes->categoryCode,
            sendsOtherName: $changes->sendsOtherName,
            otherName: $changes->otherName,
            current: $transaction->category,
            currentOtherName: $transaction->other_name,
        ));

        $transaction->category_id = $chosen->category->id;
        $transaction->other_name = $chosen->otherName;
        $transaction->amount = $changes->amount ?? $transaction->amount;
        $transaction->date = $changes->date ?? $transaction->date;

        if ($changes->sendsComment) {
            $transaction->comment = $changes->comment;
        }

        $transaction->updated_by = $user->id;
        $transaction->save();
        $transaction->setRelation('category', $chosen->category);

        return new TransactionView($transaction, $this->nameAccounts->handle([$transaction]));
    }
}
