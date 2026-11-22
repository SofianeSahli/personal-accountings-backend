<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OtherNameList extends JsonResource
{
    /**
     * @param  list<string>  $names
     */
    public function __construct(private readonly array $names)
    {
        parent::__construct($names);
    }

    /**
     * @return list<string>
     */
    public function toArray(Request $request): array
    {
        return $this->names;
    }
}
