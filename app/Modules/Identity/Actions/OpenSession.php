<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\SignInResult;
use App\Modules\Identity\Support\SignInTarget;

final class OpenSession
{
    public function __construct(
        private readonly IssueDeviceSession $issueDeviceSession,
        private readonly StartWebSession $startWebSession,
    ) {}

    public function handle(User $user, SignInTarget $target): SignInResult
    {
        if ($target->deviceId === null) {
            $this->startWebSession->handle($user);

            return SignInResult::signedIn($user, null);
        }

        return SignInResult::signedIn($user, $this->issueDeviceSession->handle($user, $target->deviceId));
    }
}
