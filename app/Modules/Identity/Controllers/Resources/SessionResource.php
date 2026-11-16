<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers\Resources;

use App\Modules\Identity\Support\SignInResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SessionResource extends JsonResource
{
    public function __construct(private readonly SignInResult $result)
    {
        parent::__construct($result);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->result->pendingSignIn !== null) {
            return [
                'status' => 'code_required',
                'pending_sign_in' => $this->result->pendingSignIn,
                'expires_in_seconds' => $this->result->expiresInSeconds,
            ];
        }

        $signedIn = ['status' => 'signed_in'];

        if ($this->result->token !== null) {
            $signedIn['token'] = $this->result->token;
        }

        if ($this->result->user !== null) {
            $signedIn['account'] = new AccountResource($this->result->user);
        }

        return $signedIn;
    }
}
