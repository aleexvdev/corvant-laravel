<?php

declare(strict_types=1);

namespace Corvant\Adapters\Notification\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PasswordResetMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $token,
        public ?string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Password reset',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'corvant::mail.password-reset',
            with: [
                'token' => $this->token,
                'url' => $this->actionUrl,
            ],
        );
    }
}
