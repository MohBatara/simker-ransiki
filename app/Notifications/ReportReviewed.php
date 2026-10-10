<?php

namespace App\Notifications;

use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Support\NotificationText;
use Illuminate\Notifications\Notification;

/**
 * Untuk akun kelompok saat laporannya diverifikasi, diminta revisi, atau dibuka kembali oleh Admin.
 */
class ReportReviewed extends Notification
{
    public function __construct(private MonthlyReport $report, private ReviewAction $action, private ?string $note) {}

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
        ];
    }
}
