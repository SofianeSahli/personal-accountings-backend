<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategorieResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'name' => $this->name, // translation key
            'description' => $this->description, // translation key
            'is_income' => $this->is_income,
            'children' => CategorieResource::collection(
                $this->whenLoaded('children') // load subcategories
            ),
        ];

    }
}
