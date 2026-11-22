<?php

declare(strict_types=1);

use App\Modules\Household\Actions\InviteMember;
use App\Modules\Household\Mail\InvitationMail;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\freezeSecond;
use function Pest\Laravel\travel;
use function Pest\Laravel\travelTo;

/**
 * @return array{0: User, 1: Household, 2: Closure(string): HouseholdInvitation}
 */
function invitingOwner(): array
{
    Mail::fake();
    $owner = User::factory()->create(['email' => 'owner@example.com', 'locale' => 'en']);
    $household = HouseholdScenario::ownedBy($owner);

    return [
        $owner,
        $household,
        fn (string $email): HouseholdInvitation => app(InviteMember::class)->handle($owner, $household, $email),
    ];
}

it('REQ-1.1 records a pending invitation, lowercased, expiring in 7 days', function (): void {
    freezeSecond();
    [$owner, , $invite] = invitingOwner();

    $invitation = $invite('  Amira@Example.COM ');

    expect($invitation->email)->toBe('amira@example.com')
        ->and($invitation->status)->toBe(InvitationStatus::Pending)
        ->and($invitation->invited_by)->toBe($owner->id)
        ->and($invitation->expires_at->equalTo(now()->addDays(7)))->toBeTrue();
});

it('REQ-1.2 queues one notice to the address, in the inviter\'s locale', function (): void {
    [$owner, $household, $invite] = invitingOwner();

    $invite('amira@example.com');

    Mail::assertQueued(InvitationMail::class, 1);
    Mail::assertQueued(InvitationMail::class, fn (InvitationMail $mail): bool => $mail->hasTo('amira@example.com')
        && $mail->locale === 'en'
        && $mail->inviterName === $owner->name
        && $mail->householdName === $household->name);
});

it('REQ-1.3 invites an address whose account belongs to another household like any other', function (): void {
    [, , $invite] = invitingOwner();
    HouseholdScenario::ownedBy(User::factory()->create(['email' => 'busy@example.com']));

    expect($invite('busy@example.com')->status)->toBe(InvitationStatus::Pending);
    Mail::assertQueued(InvitationMail::class, 1);
});

it('REQ-1.4 refuses the owner\'s own address', function (): void {
    [, , $invite] = invitingOwner();

    expect(HouseholdScenario::refusalOf(fn () => $invite('OWNER@example.com'))?->errorCode)->toBe('invitation.self');
});

it('REQ-1.5 refuses the address of a live member', function (): void {
    [, $household, $invite] = invitingOwner();
    HouseholdScenario::join($household, User::factory()->create(['email' => 'member@example.com']));

    expect(HouseholdScenario::refusalOf(fn () => $invite('member@example.com'))?->errorCode)->toBe('invitation.already_member');
});

it('REQ-1.5 invites a former member again', function (): void {
    [, $household, $invite] = invitingOwner();
    $former = User::factory()->create(['email' => 'former@example.com']);
    HouseholdScenario::join($household, $former);
    HouseholdScenario::leave($former);

    expect($invite('former@example.com')->status)->toBe(InvitationStatus::Pending);
});

it('REQ-1.6 refuses a second pending invitation for the same address, in any letter case', function (): void {
    [, , $invite] = invitingOwner();
    $invite('amira@example.com');

    expect(HouseholdScenario::refusalOf(fn () => $invite('Amira@Example.com'))?->errorCode)->toBe('invitation.already_pending')
        ->and(HouseholdInvitation::query()->count())->toBe(1);
});

it('REQ-1.6 lets an expired or closed invitation be followed by a new one', function (string $state): void {
    [, $household, $invite] = invitingOwner();
    HouseholdScenario::invite($household, 'amira@example.com', HouseholdInvitation::factory()->{$state}());

    expect($invite('amira@example.com')->status)->toBe(InvitationStatus::Pending);
})->with(['expired', 'accepted', 'declined', 'cancelled']);

it('REQ-1.7 refuses when the household has 10 members, owner included', function (): void {
    [, $household, $invite] = invitingOwner();
    foreach (range(1, Household::MEMBER_LIMIT - 1) as $ignored) {
        HouseholdScenario::memberOf($household);
    }

    expect(HouseholdScenario::refusalOf(fn () => $invite('amira@example.com'))?->errorCode)->toBe('household.member_limit');
});

it('REQ-1.8 refuses an 11th pending invitation, expired ones not counted', function (): void {
    [, $household, $invite] = invitingOwner();
    HouseholdScenario::invite($household, 'old@example.com', HouseholdInvitation::factory()->expired());
    foreach (range(1, HouseholdInvitation::PENDING_LIMIT) as $index) {
        HouseholdScenario::invite($household, "guest{$index}@example.com");
    }

    expect(HouseholdScenario::refusalOf(fn () => $invite('amira@example.com'))?->errorCode)->toBe('invitation.limit_reached');
});

it('REQ-1.9 refuses the 21st invitation in 24 hours with the seconds until one frees up', function (): void {
    freezeSecond();
    [, $household, $invite] = invitingOwner();
    $start = now();
    foreach (range(1, HouseholdInvitation::DAILY_LIMIT) as $index) {
        HouseholdScenario::invite($household, "guest{$index}@example.com", HouseholdInvitation::factory()->cancelled());
        travel(1)->minutes();
    }

    $refusal = HouseholdScenario::refusalOf(fn () => $invite('amira@example.com'));

    expect($refusal?->errorCode)->toBe('request.throttled')
        ->and($refusal?->status)->toBe(429)
        ->and($refusal?->retryAfterSeconds)->toBe((int) now()->diffInSeconds($start->copy()->addDay()))
        ->and($refusal?->headers)->toBe(['Retry-After' => (string) $refusal?->retryAfterSeconds]);

    travelTo($start->copy()->addDay()->addSecond());
    expect($invite('amira@example.com')->status)->toBe(InvitationStatus::Pending);
});

it('REQ-1.11 checks the address before the limits', function (): void {
    [, $household, $invite] = invitingOwner();
    foreach (range(1, Household::MEMBER_LIMIT - 1) as $ignored) {
        HouseholdScenario::memberOf($household);
    }

    expect(HouseholdScenario::refusalOf(fn () => $invite('owner@example.com'))?->errorCode)->toBe('invitation.self');
});

it('REQ-1.11 records nothing and mails nothing when it refuses', function (): void {
    [, , $invite] = invitingOwner();

    HouseholdScenario::refusalOf(fn () => $invite('owner@example.com'));

    expect(HouseholdInvitation::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});
