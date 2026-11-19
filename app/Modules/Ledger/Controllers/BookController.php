<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Actions\CreateBook;
use App\Modules\Ledger\Actions\ListBooks;
use App\Modules\Ledger\Actions\UpdateBook;
use App\Modules\Ledger\Controllers\Requests\CreateBookRequest;
use App\Modules\Ledger\Controllers\Requests\UpdateBookRequest;
use App\Modules\Ledger\Controllers\Resources\BookList;
use App\Modules\Ledger\Controllers\Resources\BookResource;
use App\Modules\Ledger\Support\CurrentBook;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class BookController
{
    public function index(#[CurrentUser] User $user, ListBooks $listBooks): BookList
    {
        return new BookList($listBooks->handle($user));
    }

    public function store(CreateBookRequest $request, #[CurrentUser] User $user, CreateBook $createBook): JsonResponse
    {
        $book = $createBook->handle($user, $request->newBook());

        return (new BookResource($book, true))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(CurrentBook $current): BookResource
    {
        return new BookResource($current->book, $current->canManage);
    }

    public function update(UpdateBookRequest $request, CurrentBook $current, UpdateBook $updateBook): BookResource
    {
        $book = $updateBook->handle($current->book, $request->changes());

        return new BookResource($book, $current->canManage);
    }
}
