<?php

declare(strict_types=1);

use App\Modules\Household\Mail\InvitationMail;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('REQ-8.6 writes no address, name or QR token to the logs', function (): void {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });
    Mail::fake();
    $owner = User::factory()->create(['name' => 'Owner Secret-Name', 'email' => 'owner.private@example.com']);
    $invitee = User::factory()->create(['name' => 'Invitee Secret-Name', 'email' => 'invitee.private@example.com']);
    $scanner = User::factory()->create(['name' => 'Scanner Secret-Name', 'email' => 'scanner.private@example.com']);
    $household = HouseholdScenario::ownedBy($owner, 'Secret Household');
    $base = "/api/v1/households/{$household->id}";

    $invitationId = (string) postJson("{$base}/invitations", ['email' => 'invitee.private@example.com'], HouseholdScenario::as($owner))
        ->assertCreated()
        ->json('data.id');
    postJson("{$base}/invitations", ['email' => 'invitee.private@example.com'], HouseholdScenario::as($owner))->assertStatus(422);
    getJson('/api/v1/invitations', HouseholdScenario::as($invitee))->assertOk();
    postJson("/api/v1/invitations/{$invitationId}/accept", [], HouseholdScenario::as($invitee))->assertOk();
    $token = (string) postJson("{$base}/qr-invitations", [], HouseholdScenario::as($owner))->assertCreated()->json('data.token');
    postJson('/api/v1/qr-invitations/preview', ['token' => $token], HouseholdScenario::as($scanner))->assertOk();
    postJson('/api/v1/qr-invitations/accept', ['token' => $token], HouseholdScenario::as($scanner))->assertOk();
    postJson('/api/v1/qr-invitations/accept', ['token' => $token], HouseholdScenario::as($scanner))->assertStatus(404);
    deleteJson("{$base}/members/{$invitee->id}", [], HouseholdScenario::as($owner))->assertNoContent();
    postJson("{$base}/leave", [], HouseholdScenario::as($scanner))->assertNoContent();

    expect($token)->not->toBe('');
    foreach ($logged as $line) {
        expect($line)->not->toContain('Secret')
            ->and($line)->not->toContain('private@example.com')
            ->and($line)->not->toContain($token)
            ->and($line)->not->toContain(hash('sha256', $token));
    }
});

it('REQ-8.6 logs no address or name when the notice email cannot be sent', function (): void {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });
    config([
        'queue.default' => 'sync',
        'mail.mailers.unreachable' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1],
    ]);

    try {
        Mail::mailer('unreachable')
            ->to('invitee.private@example.com')
            ->send(new InvitationMail('Owner Secret-Name', 'Secret Household'));
    } catch (Throwable $failure) {
        report($failure);
    }

    expect($logged)->not->toBe([]);
    foreach ($logged as $line) {
        expect($line)->not->toContain('Secret')
            ->and($line)->not->toContain('private@example.com');
    }
});
