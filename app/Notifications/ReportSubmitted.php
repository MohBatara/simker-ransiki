<?php

namespace App\Notifications;

use App\Models\MonthlyReport;
use App\Support\NotificationText;
use Illuminate\Notifications\Notification;

/**
 * Untuk akun OPD pembina (atau Admin bila kelompok tanpa OPD) saat kelompok mengajukan laporan.
 * Kalimatnya disusun App\Support\NotificationText saat ditampilkan.
 */
class ReportSubmitted extends Notification
{
    public function __construct(private MonthlyReport $report, private bool $resubmitted = false) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'report_submitted',
            ...NotificationText::reportData($this->report),
            'resubmitted' => $this->resubmitted,
        ];
    }
}
