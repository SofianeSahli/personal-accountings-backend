<?php

declare(strict_types=1);

namespace App\Shared\Health;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HealthResource extends JsonResource
{
    public function __construct(private readonly string $status)
    {
        parent::__construct($status);
    }

    /**
     * @return array{status: string}
     */
    public function toArray(Request $request): array
    {
        return ['status' => $this->status];
    }
}
