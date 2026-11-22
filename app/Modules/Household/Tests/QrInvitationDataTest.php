<?php

declare(strict_types=1);

use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('REQ-9.2 makes random, URL-safe 43-character tokens', function (): void {
    $tokens = array_map(fn (): string => HouseholdQrInvitation::newToken(), range(1, 20));

    expect(array_unique($tokens))->toHaveCount(20);
    foreach ($tokens as $token) {
        expect($token)->toMatch('/^[A-Za-z0-9_-]{43}$/');
    }
});

it('REQ-9.2 stores only the token hash and finds the row by the token', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $qr = HouseholdScenario::qrInvitation($household, 'known-token');

    $row = (array) DB::table('household_qr_invitations')->sole();

    expect($row['token_hash'])->toBe(hash('sha256', 'known-token'))
        ->and(implode(' ', array_map('strval', $row)))->not->toContain('known-token')
        ->and(HouseholdQrInvitation::findByToken('known-token')?->id)->toBe($qr->id)
        ->and(HouseholdQrInvitation::findByToken('other-token'))->toBeNull()
        ->and($qr->toArray())->not->toHaveKey('token_hash');
});

it('REQ-9.2 refuses two rows with the same token hash', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    HouseholdScenario::qrInvitation($household, 'same-token');

    expect(fn () => HouseholdScenario::qrInvitation($household, 'same-token'))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('REQ-10.3 counts as pending only open, unexpired QR invitations of a live household', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $open = HouseholdScenario::qrInvitation($household, 'open');
    HouseholdScenario::qrInvitation($household, 'expired', HouseholdQrInvitation::factory()->expired());
    HouseholdScenario::qrInvitation($household, 'accepted', HouseholdQrInvitation::factory()->accepted());
    HouseholdScenario::qrInvitation($household, 'cancelled', HouseholdQrInvitation::factory()->cancelled());
    $deleted = HouseholdScenario::ownedBy(User::factory()->create());
    HouseholdScenario::qrInvitation($deleted, 'deleted');
    $deleted->delete();

    expect(HouseholdQrInvitation::query()->pending()->pluck('id')->all())->toBe([$open->id]);
});
