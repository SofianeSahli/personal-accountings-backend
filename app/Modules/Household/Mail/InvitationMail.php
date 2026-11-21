<?php

declare(strict_types=1);

namespace App\Modules\Household\Mail;

use App\Modules\Household\Models\HouseholdInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class InvitationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $inviterName,
        public readonly string $householdName,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('household::mail.invitation.subject'));
    }

    public function content(): Content
    {
        return new Content(
            text: 'household::mail.invitation',
            with: ['validDays' => HouseholdInvitation::VALID_DAYS],
        );
    }
}
