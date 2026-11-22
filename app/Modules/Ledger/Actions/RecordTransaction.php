<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\CategoryChoice;
use App\Modules\Ledger\Support\NewTransaction;
use App\Modules\Ledger\Support\RecordedTransaction;
use App\Modules\Ledger\Support\TransactionView;
use App\Shared\Errors\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;

final class RecordTransaction
{
    public function __construct(
        private readonly ChooseCategory $chooseCategory,
        private readonly NameAccounts $nameAccounts,
    ) {}

    public function handle(Book $book, User $user, NewTransaction $new): RecordedTransaction
    {
        $existing = $new->id === null ? null : $this->existing($book, $new->id);

        if ($existing !== null) {
            return $this->recorded($existing, false);
        }

        $chosen = $this->chooseCategory->handle(CategoryChoice::forNew($new->categoryCode, $new->otherName));

        $attributes = [
            'book_id' => $book->id,
            'category_id' => $chosen->category->id,
            'amount' => $new->amount,
            'date' => $new->date,
            'other_name' => $chosen->otherName,
            'comment' => $new->comment,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        if ($new->id !== null) {
            $attributes['id'] = $new->id;
        }

        try {
            $transaction = Transaction::query()->create($attributes);
        } catch (UniqueConstraintViolationException $race) {
            $existing = $new->id === null ? null : $this->existing($book, $new->id);

            if ($existing === null) {
                throw $race;
            }

            return $this->recorded($existing, false);
        }

        $transaction->setRelation('category', $chosen->category);

        return $this->recorded($transaction, true);
    }

    private function existing(Book $book, string $id): ?Transaction
    {
        $found = Transaction::withTrashed()->with('category')->find($id);

        if ($found === null) {
            return null;
        }

        if ($found->book_id !== $book->id || $found->trashed()) {
            throw new ApiException('transaction.id_conflict', Response::HTTP_CONFLICT);
        }

        return $found;
    }

    private function recorded(Transaction $transaction, bool $created): RecordedTransaction
    {
        return new RecordedTransaction(
            new TransactionView($transaction, $this->nameAccounts->handle([$transaction])),
            $created,
        );
    }
}
