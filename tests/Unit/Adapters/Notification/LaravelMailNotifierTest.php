<?php

declare(strict_types=1);

use Corvant\Adapters\Notification\LaravelMailNotifier;
use Corvant\Adapters\Notification\Mail\EmailChangeConfirmationMail;
use Corvant\Adapters\Notification\Mail\EmailVerificationMail;
use Corvant\Adapters\Notification\Mail\PasswordResetMail;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
});

it('sends password reset mail with a substituted action url when configured', function (): void {
    config(['corvant.notifications.password_reset_url' => 'https://app.test/reset?token={token}']);

    $notifier = new LaravelMailNotifier();
    $notifier->sendPasswordResetLink(new Email('reset@example.com'), 'secret-token');

    Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail): bool {
        return $mail->hasTo('reset@example.com')
            && $mail->token === 'secret-token'
            && $mail->actionUrl === 'https://app.test/reset?token=secret-token';
    });
});

it('sends password reset mail without an action url when not configured', function (): void {
    config(['corvant.notifications.password_reset_url' => null]);

    $notifier = new LaravelMailNotifier();
    $notifier->sendPasswordResetLink(new Email('reset@example.com'), 'plain-token');

    Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail): bool {
        return $mail->token === 'plain-token' && $mail->actionUrl === null;
    });
});

it('sends email verification mail with a substituted action url when configured', function (): void {
    config(['corvant.notifications.email_verification_url' => 'https://app.test/verify/{token}']);

    $notifier = new LaravelMailNotifier();
    $notifier->sendEmailVerificationLink(new Email('verify@example.com'), 'verify-token');

    Mail::assertSent(EmailVerificationMail::class, function (EmailVerificationMail $mail): bool {
        return $mail->hasTo('verify@example.com')
            && $mail->token === 'verify-token'
            && $mail->actionUrl === 'https://app.test/verify/verify-token';
    });
});

it('sends email verification mail without an action url when not configured', function (): void {
    config(['corvant.notifications.email_verification_url' => null]);

    $notifier = new LaravelMailNotifier();
    $notifier->sendEmailVerificationLink(new Email('verify@example.com'), 'verify-token');

    Mail::assertSent(EmailVerificationMail::class, function (EmailVerificationMail $mail): bool {
        return $mail->actionUrl === null && $mail->token === 'verify-token';
    });
});

it('sends email change confirmation mail with a substituted action url when configured', function (): void {
    config(['corvant.notifications.email_change_confirmation_url' => 'https://app.test/email-change?code={token}']);

    $notifier = new LaravelMailNotifier();
    $notifier->sendEmailChangeConfirmationLink(new Email('change@example.com'), 'change-token');

    Mail::assertSent(EmailChangeConfirmationMail::class, function (EmailChangeConfirmationMail $mail): bool {
        return $mail->hasTo('change@example.com')
            && $mail->token === 'change-token'
            && $mail->actionUrl === 'https://app.test/email-change?code=change-token';
    });
});

it('sends email change confirmation mail without an action url when not configured', function (): void {
    config(['corvant.notifications.email_change_confirmation_url' => null]);

    $notifier = new LaravelMailNotifier();
    $notifier->sendEmailChangeConfirmationLink(new Email('change@example.com'), 'change-token');

    Mail::assertSent(EmailChangeConfirmationMail::class, function (EmailChangeConfirmationMail $mail): bool {
        return $mail->actionUrl === null && $mail->token === 'change-token';
    });
});
