<?php

declare(strict_types=1);

namespace Corvant\Adapters\Notification;

use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Ports\NotificationPort;
use Illuminate\Support\Facades\Mail;

final class LaravelMailNotifier implements NotificationPort
{
    public function sendPasswordResetLink(Email $to, string $token): void
    {
        Mail::raw(
            "Use this token to reset your password: {$token}",
            static function ($message) use ($to): void {
                $message->to($to->value())
                    ->subject('Password reset');
            },
        );
    }

    public function sendEmailVerificationLink(Email $to, string $token): void
    {
        Mail::raw(
            "Use this token to verify your email: {$token}",
            static function ($message) use ($to): void {
                $message->to($to->value())
                    ->subject('Verify your email');
            },
        );
    }

    public function sendEmailChangeConfirmationLink(Email $to, string $token): void
    {
        Mail::raw(
            "Use this token to confirm your new email address: {$token}",
            static function ($message) use ($to): void {
                $message->to($to->value())
                    ->subject('Confirm your new email');
            },
        );
    }
}
