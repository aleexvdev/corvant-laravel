<?php

declare(strict_types=1);

namespace Corvant\Adapters\Notification;

use Corvant\Adapters\Notification\Mail\EmailChangeConfirmationMail;
use Corvant\Adapters\Notification\Mail\EmailVerificationMail;
use Corvant\Adapters\Notification\Mail\PasswordResetMail;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Ports\NotificationPort;
use Illuminate\Support\Facades\Mail;

final class LaravelMailNotifier implements NotificationPort
{
    public function sendPasswordResetLink(Email $to, string $token): void
    {
        $url = $this->resolveActionUrl(
            config('corvant.notifications.password_reset_url'),
            $token,
        );

        Mail::to($to->value())->send(new PasswordResetMail($token, $url));
    }

    public function sendEmailVerificationLink(Email $to, string $token): void
    {
        $url = $this->resolveActionUrl(
            config('corvant.notifications.email_verification_url'),
            $token,
        );

        Mail::to($to->value())->send(new EmailVerificationMail($token, $url));
    }

    public function sendEmailChangeConfirmationLink(Email $to, string $token): void
    {
        $url = $this->resolveActionUrl(
            config('corvant.notifications.email_change_confirmation_url'),
            $token,
        );

        Mail::to($to->value())->send(new EmailChangeConfirmationMail($token, $url));
    }

    private function resolveActionUrl(mixed $template, string $token): ?string
    {
        if (! is_string($template) || $template === '') {
            return null;
        }

        return str_replace('{token}', $token, $template);
    }
}
