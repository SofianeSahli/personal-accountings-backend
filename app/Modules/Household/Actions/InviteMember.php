<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Mail\InvitationMail;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Identity\Actions\Accounts\FindAccountIdByEmail;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

final class InviteMember
{
    private const RETRY_AFTER_HEADER = 'Retry-After';

    public function __construct(private readonly FindAccountIdByEmail $findAccountIdByEmail) {}

    public function handle(User $inviter, Household $household, string $email): HouseholdInvitation
    {
        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($inviter, $household, $email): HouseholdInvitation {
            $locked = Household::lockLive($household->id)
                ?? throw new ApiException('household.not_found', Response::HTTP_NOT_FOUND);

            $this->refuseKnownAccount($inviter, $locked, $email);
            $this->refuseDuplicate($locked, $email);
            $this->refuseOverLimits($locked);

            $invitation = HouseholdInvitation::query()->create([
                'household_id' => $locked->id,
                'email' => $email,
                'invited_by' => $inviter->id,
                'status' => InvitationStatus::Pending,
                'expires_at' => now()->addDays(HouseholdInvitation::VALID_DAYS),
            ]);

            Mail::to($email)
                ->locale($inviter->localeCode())
                ->queue(new InvitationMail($inviter->name, $locked->name));

            return $invitation;
        });
    }

    private function refuseKnownAccount(User $inviter, Household $household, string $email): void
    {
        $accountId = $this->findAccountIdByEmail->handle($email);

        if ($accountId === $inviter->id) {
            throw $this->refusal('invitation.self');
        }

        if ($accountId !== null && $household->members()->where('user_id', $accountId)->exists()) {
            throw $this->refusal('invitation.already_member');
        }
    }

    private function refuseDuplicate(Household $household, string $email): void
    {
        $pending = HouseholdInvitation::query()
            ->pending()
            ->where('household_id', $household->id)
            ->where('email', $email)
            ->exists();

        if ($pending) {
            throw $this->refusal('invitation.already_pending');
        }
    }

    private function refuseOverLimits(Household $household): void
    {
        if ($household->memberCount() >= Household::MEMBER_LIMIT) {
            throw $this->refusal('household.member_limit');
        }

        $pendingCount = HouseholdInvitation::query()->pending()->where('household_id', $household->id)->count();

        if ($pendingCount >= HouseholdInvitation::PENDING_LIMIT) {
            throw $this->refusal('invitation.limit_reached');
        }

        $this->refuseOverDailyLimit($household);
    }

    private function refuseOverDailyLimit(Household $household): void
    {
        $recent = HouseholdInvitation::query()
            ->where('household_id', $household->id)
            ->where('created_at', '>', now()->subHours(HouseholdInvitation::DAILY_WINDOW_HOURS))
            ->orderBy('created_at')
            ->get(['created_at']);

        if ($recent->count() < HouseholdInvitation::DAILY_LIMIT) {
            return;
        }

        $seconds = $this->secondsUntilFree($recent->first()->created_at ?? now());

        throw new ApiException(
            'request.throttled',
            Response::HTTP_TOO_MANY_REQUESTS,
            retryAfterSeconds: $seconds,
            headers: [self::RETRY_AFTER_HEADER => (string) $seconds],
        );
    }

    private function secondsUntilFree(CarbonInterface $oldest): int
    {
        $freeAt = $oldest->copy()->addHours(HouseholdInvitation::DAILY_WINDOW_HOURS);

        return max(1, (int) ceil(now()->diffInSeconds($freeAt)));
    }

    private function refusal(string $code): ApiException
    {
        return new ApiException($code, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
