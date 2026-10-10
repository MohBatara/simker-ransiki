<?php

namespace App\Notifications;

use App\Models\MonthlyReport;
use App\Support\Format;
use Illuminate\Notifications\Notification;

/**
 * Untuk akun OPD pembina saat kelompok mengajukan laporan. Ditampilkan di halaman notifikasi (langkah 11).
 */
class ReportSubmitted extends Notification
{
    public function __construct(private MonthlyReport $report) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'monthly_report_id' => $this->report->id,
            'organization_id' => $this->report->organization_id,
            'message' => $this->report->organization->name.' mengajukan laporan '.Format::month($this->report->period).'.',
            'url' => route('opd.reports.show', $this->report, absolute: false),
        ];
    }
}
