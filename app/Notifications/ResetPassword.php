<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email "Lupa kata sandi" (password broker Laravel) dengan template Bahasa Indonesia. */
class ResetPassword extends Notification
{
    public function __construct(private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Atur ulang kata sandi SIM-KER')
            ->view(['emails.reset-password', 'emails.reset-password-text'], [
                'user' => $notifiable,
                'url' => route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]),
                'minutes' => (int) config('auth.passwords.users.expire'),
            ]);
    }
}
