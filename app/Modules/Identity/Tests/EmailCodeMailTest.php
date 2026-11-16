<?php

declare(strict_types=1);

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\Locale;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

it('REQ-9.2 writes the subject and text in the chosen locale, with the code and its minutes', function (
    Locale $locale,
    EmailCodePurpose $purpose,
    string $subject,
    string $minutesText,
): void {
    $mail = (new EmailCodeMail('123456', $purpose))->locale($locale->value);

    $mail->assertHasSubject($subject)
        ->assertSeeInText('123456')
        ->assertSeeInText($minutesText);
})->with([
    'French confirmation' => [Locale::Fr, EmailCodePurpose::EmailConfirmation, 'Votre code de confirmation', 'valable 15 minutes'],
    'French sign-in' => [Locale::Fr, EmailCodePurpose::SignIn, 'Votre code de connexion', 'valable 10 minutes'],
    'English reset' => [Locale::En, EmailCodePurpose::PasswordReset, 'Your password reset code', 'valid for 15 minutes'],
    'English sign-in' => [Locale::En, EmailCodePurpose::SignIn, 'Your sign-in code', 'valid for 10 minutes'],
]);

it('REQ-9.3 has a subject for every purpose in every locale', function (Locale $locale): void {
    foreach (EmailCodePurpose::cases() as $purpose) {
        $key = 'identity::mail.subject.'.$purpose->value;

        expect(trans()->hasForLocale($key, $locale->value))->toBeTrue();
    }

    expect(trans()->hasForLocale('identity::mail.body', $locale->value))->toBeTrue();
})->with(Locale::cases());

it('REQ-9.4 is queued and encrypted on the queue', function (): void {
    $mail = new EmailCodeMail('123456', EmailCodePurpose::PasswordReset);

    expect($mail)->toBeInstanceOf(ShouldQueue::class);
    expect($mail)->toBeInstanceOf(ShouldBeEncrypted::class);
});
