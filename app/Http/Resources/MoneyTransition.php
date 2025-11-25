<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MoneyTransition extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'amount' => $this->amount,
            'description' => $this->description,
            'categorie' => new CategorieResource($this->categorie),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'id' => $this->id,
        ];
    }
}
