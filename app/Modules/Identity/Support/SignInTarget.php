<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

final readonly class SignInTarget
{
    private function __construct(
        public SignInMode $mode,
        public ?string $deviceId,
    ) {}

    public static function web(): self
    {
        return new self(SignInMode::Web, null);
    }

    public static function device(string $deviceId): self
    {
        return new self(SignInMode::Device, strtolower($deviceId));
    }

    public static function ofPendingSignIn(?string $deviceId): self
    {
        return $deviceId === null ? self::web() : self::device($deviceId);
    }
}
