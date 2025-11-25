<?php

namespace App\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;

class CustomLengthAwarePaginator extends LengthAwarePaginator
{
    public function toArray(): array
    {
        return [
            'items' => $this->items(),      // items directly
            'total' => $this->total(),
            'totalItems' => $this->count(),
            'perPage' => $this->perPage(),
            'page' => $this->currentPage(),
            'totalPages' => $this->lastPage(),
        ];
    }
}
