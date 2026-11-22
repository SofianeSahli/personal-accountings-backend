<?php

declare(strict_types=1);

use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;

use function Pest\Laravel\postJson;

it('REQ-10.1 shows the household and the inviter, and changes nothing (token mode)', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create(['name' => 'Sofiane']), 'Foyer Sahli');
    $qr = HouseholdScenario::qrInvitation($household, 'scan-me');
    $scanner = User::factory()->create();

    postJson('/api/v1/qr-invitations/preview', ['token' => 'scan-me'], HouseholdScenario::as($scanner))
        ->assertOk()
        ->assertExactJson(['data' => [
            'household' => ['id' => $household->id, 'name' => 'Foyer Sahli'],
            'invited_by' => ['name' => 'Sofiane'],
            'expires_at' => $qr->expires_at->toIso8601ZuluString(),
        ]]);

    expect($qr->refresh()->status)->toBe(InvitationStatus::Pending);
});

it('REQ-10.1 shows the household (cookie mode)', function (): void {
    HouseholdScenario::qrInvitation(HouseholdScenario::ownedBy(User::factory()->create()), 'scan-me');

    WebBrowser::signedInAs(User::factory()->create())
        ->postJson('/api/v1/qr-invitations/preview', ['token' => 'scan-me'])
        ->assertOk();
});

it('REQ-10.5 gives a code for a missing or invalid token', function (mixed $token, string $code): void {
    postJson('/api/v1/qr-invitations/preview', ['token' => $token], HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['token' => [$code]]]]);
})->with([
    'missing' => [null, 'token.required'],
    'blank' => ['  ', 'token.required'],
    'not a string' => [123, 'token.string'],
]);

it('REQ-10.3 answers the same 404 for every token that cannot be used', function (string $case): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $scanner = User::factory()->create();
    match ($case) {
        'used' => HouseholdScenario::qrInvitation($household, 'scan-me', HouseholdQrInvitation::factory()->accepted()),
        'replaced' => HouseholdScenario::qrInvitation($household, 'scan-me', HouseholdQrInvitation::factory()->cancelled()),
        'expired' => HouseholdScenario::qrInvitation($household, 'scan-me', HouseholdQrInvitation::factory()->expired()),
        'household deleted' => [HouseholdScenario::qrInvitation($household, 'scan-me'), $household->delete()],
        'unconfirmed email' => [HouseholdScenario::qrInvitation($household, 'scan-me'), $scanner->update(['email_verified_at' => null])],
        default => null,
    };

    postJson('/api/v1/qr-invitations/preview', ['token' => 'scan-me'], HouseholdScenario::as($scanner))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'invitation.not_found']]);
})->with(['unknown', 'used', 'replaced', 'expired', 'household deleted', 'unconfirmed email']);

it('REQ-8.1 answers 401 without a session or token', function (): void {
    postJson('/api/v1/qr-invitations/preview', ['token' => 'scan-me'])->assertStatus(401);
});
