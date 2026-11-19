<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Resources;

use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Support\BookPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class BookList extends ResourceCollection
{
    public function __construct(private readonly BookPage $books)
    {
        parent::__construct($books->page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return array_values(array_map(
            fn (Book $book): array => (new BookResource($book, $this->books->canManage($book)))->toArray($request),
            $this->books->page->items(),
        ));
    }
}
