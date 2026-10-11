<?php

namespace App\Notifications;

use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Support\NotificationText;
use Illuminate\Notifications\Notification;

/**
 * Saat laporan diverifikasi, diminta revisi, atau dibuka kembali: untuk akun kelompok, dan untuk OPD pembina /
 * pengisi atas nama kelompok (audience "reviewer", kalimatnya menyebut kelompok dan peninjaunya).
 */
class ReportReviewed extends Notification
{
    public function __construct(private MonthlyReport $report, private ReviewAction $action, private ?string $note, private ?User $actor = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'report_reviewed',
            ...NotificationText::reportData($this->report),
            'action' => $this->action->value,
            'note' => $this->note,
            'audience' => $notifiable instanceof User && ! $notifiable->isKelompok() ? 'reviewer' : 'kelompok',
            'actor_name' => $this->actor?->nameWithAgency(),
        ];
    }
}
