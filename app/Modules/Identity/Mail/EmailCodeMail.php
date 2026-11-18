<?php

declare(strict_types=1);

namespace App\Modules\Identity\Mail;

use App\Modules\Identity\Models\EmailCode;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\PendingSignIn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class EmailCodeMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $code,
        public readonly EmailCodePurpose $purpose,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('identity::mail.subject.'.$this->purpose->value));
    }

    public function content(): Content
    {
        return new Content(
            text: 'identity::mail.email-code',
            with: ['validMinutes' => $this->validMinutes()],
        );
    }

    private function validMinutes(): int
    {
        return $this->purpose === EmailCodePurpose::SignIn
            ? PendingSignIn::VALID_MINUTES
            : EmailCode::VALID_MINUTES;
    }
}
