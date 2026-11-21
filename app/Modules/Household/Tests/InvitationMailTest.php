<?php

declare(strict_types=1);

use App\Modules\Household\Mail\InvitationMail;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

it('REQ-1.2 is queued, encrypted on the queue, and sent only after commit', function (): void {
    $mail = new InvitationMail('Sofiane', 'Foyer Sahli');

    expect($mail)->toBeInstanceOf(ShouldQueue::class)
        ->and($mail)->toBeInstanceOf(ShouldBeEncrypted::class)
        ->and($mail->afterCommit)->toBeTrue();
});

it('REQ-1.2 names the inviter and the household, in English', function (): void {
    $mail = (new InvitationMail('Sofiane', 'Foyer & Co'))->locale('en');

    $mail->assertHasSubject('You are invited to join a household');
    $mail->assertSeeInText('Sofiane invited you to join the household "Foyer & Co".');
    $mail->assertSeeInText('sign in or sign up in the app with this email address');
    $mail->assertSeeInText('It expires in 7 days.');
});

it('REQ-1.2 names the inviter and the household, in French', function (): void {
    $mail = (new InvitationMail('Sofiane', 'Foyer Sahli'))->locale('fr');

    $mail->assertHasSubject('Vous êtes invité(e) à rejoindre un foyer');
    $mail->assertSeeInText('Sofiane vous invite à rejoindre le foyer « Foyer Sahli ».');
    $mail->assertSeeInText('Elle expire dans 7 jours.');
});

it('REQ-1.2 carries no link, token or code', function (): void {
    $mail = (new InvitationMail('Sofiane', 'Foyer Sahli'))->locale('en');

    $mail->assertDontSeeInText('http');
    $mail->assertDontSeeInText('token');
    expect(preg_match('/\d{6}/', $mail->locale('en')->render()))->toBe(0);
});
