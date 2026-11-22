<?php

declare(strict_types=1);

use App\Modules\Household\Mail\InvitationMail;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\freezeSecond;
use function Pest\Laravel\postJson;

beforeEach(fn () => Mail::fake());

it('REQ-1.1 lets the owner invite an address (token mode)', function (): void {
    freezeSecond();
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    $response = postJson("/api/v1/households/{$household->id}/invitations", ['email' => ' Amira@Example.com '], HouseholdScenario::as($owner))
        ->assertCreated();

    $invitation = HouseholdInvitation::query()->sole();
    $response->assertExactJson(['data' => [
        'id' => $invitation->id,
        'email' => 'amira@example.com',
        'created_at' => now()->toIso8601ZuluString(),
        'expires_at' => now()->addDays(7)->toIso8601ZuluString(),
    ]]);
    Mail::assertQueued(InvitationMail::class, fn (InvitationMail $mail): bool => $mail->hasTo('amira@example.com'));
});

it('REQ-1.1 lets the owner invite an address (cookie mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    WebBrowser::signedInAs($owner)
        ->postJson("/api/v1/households/{$household->id}/invitations", ['email' => 'amira@example.com'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'amira@example.com');
});

it('REQ-1.3 answers the same whether or not the address has an account', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    User::factory()->create(['email' => 'known@example.com']);

    $known = postJson("/api/v1/households/{$household->id}/invitations", ['email' => 'known@example.com'], HouseholdScenario::as($owner));
    $unknown = postJson("/api/v1/households/{$household->id}/invitations", ['email' => 'unknown@example.com'], HouseholdScenario::as($owner));

    expect($known->status())->toBe(201)
        ->and($unknown->status())->toBe(201)
        ->and(array_keys((array) $known->json('data')))->toBe(array_keys((array) $unknown->json('data')));
    Mail::assertQueued(InvitationMail::class, 2);
});

it('REQ-0.1 gives a code per invalid email', function (mixed $email, string $code): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    postJson("/api/v1/households/{$household->id}/invitations", ['email' => $email], HouseholdScenario::as($owner))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['email' => [$code]]]]);

    expect(HouseholdInvitation::query()->count())->toBe(0);
    Mail::assertNothingQueued();
})->with([
    'missing' => [null, 'email.required'],
    'blank' => ['   ', 'email.required'],
    'not a string' => [123, 'email.string'],
    'not an address' => ['not-an-email', 'email.email'],
    'too long' => [str_repeat('a', 250).'@example.com', 'email.max'],
]);

it('REQ-1.4 answers 422 invitation.self over HTTP', function (): void {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $household = HouseholdScenario::ownedBy($owner);

    postJson("/api/v1/households/{$household->id}/invitations", ['email' => 'owner@example.com'], HouseholdScenario::as($owner))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'invitation.self']]);
});

it('REQ-1.10 refuses a member with 403, before validating the body', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    postJson("/api/v1/households/{$household->id}/invitations", ['email' => 'not-an-email'], HouseholdScenario::as($member))
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'household.forbidden']]);
});

it('REQ-8.2 answers 404 for another household', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $stranger = User::factory()->create();
    HouseholdScenario::ownedBy($stranger);

    postJson("/api/v1/households/{$household->id}/invitations", ['email' => 'amira@example.com'], HouseholdScenario::as($stranger))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'household.not_found']]);
});

it('REQ-8.1 answers 401 without a session or token', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());

    postJson("/api/v1/households/{$household->id}/invitations", ['email' => 'amira@example.com'])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});
