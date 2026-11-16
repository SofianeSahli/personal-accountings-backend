<?php

declare(strict_types=1);

namespace App\Modules\Identity\Google;

interface GoogleIdentityChecker
{
    public function verify(string $idToken): GoogleIdentity;
}
