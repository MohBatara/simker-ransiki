<?php

namespace App\Notifications;

use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Support\Format;
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
        $period = Format::month($this->report->period);

        return [
            'monthly_report_id' => $this->report->id,
            'action' => $this->action->value,
            'message' => match ($this->action) {
                ReviewAction::Verified => "Laporan {$period} diverifikasi.",
                ReviewAction::RevisionRequested => "Laporan {$period} perlu revisi.",
                ReviewAction::Reopened => "Laporan {$period} dibuka kembali untuk revisi.",
                default => "Laporan {$period} ditinjau.",
            },
            'note' => $this->note,
            'url' => route('kelompok.reports.show', $this->report, absolute: false),
        ];
    }
}
