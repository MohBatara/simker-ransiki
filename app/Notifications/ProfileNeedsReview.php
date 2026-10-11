<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Untuk akun OPD pembina (atau Admin bila tanpa OPD) saat kelompok mengubah field penting profil yang sudah
 * diverifikasi. Data: organization_id, organization_name, fields (label field yang berubah).
 */
class ProfileNeedsReview extends Notification
{
    public function __construct(private array $data) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'profile_needs_review', ...$this->data];
    }
}
