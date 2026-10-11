<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email undangan akun dari Admin: tautan untuk membuat kata sandi sendiri. */
class UserInvitation extends Notification
{
    public function __construct(private string $url, private CarbonInterface $expiresAt) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Undangan akun SIM-KER Distrik Ransiki')
            ->view(['emails.invitation', 'emails.invitation-text'], [
                'user' => $notifiable,
                'url' => $this->url,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}
