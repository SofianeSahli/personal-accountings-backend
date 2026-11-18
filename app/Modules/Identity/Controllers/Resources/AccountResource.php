<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers\Resources;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AccountResource extends JsonResource
{
    public function __construct(private readonly User $user)
    {
        parent::__construct($user);
    }

    /**
     * @return array{id: string, name: string, email: string, phone: ?string, locale: string, two_factor_enabled: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'locale' => $this->user->locale->value,
            'two_factor_enabled' => $this->user->hasTwoFactor(),
        ];
    }
}
