<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Untuk semua Admin aktif saat kemungkinan data ganda kelompok terdeteksi (tambah/ubah kelompok atau impor),
 * agar diperiksa di halaman "Kemungkinan data ganda". Hanya fakta yang disimpan; kalimat di NotificationText.
 *
 * @param  array  $data  organization_id, organization_name, candidate_name, strong, others, source, actor_name
 *                       (atau untuk impor: source "impor", count)
 */
class PossibleDuplicate extends Notification
{
    public function __construct(private array $data) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'possible_duplicate', ...$this->data];
    }
}
