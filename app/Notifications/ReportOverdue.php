<?php

namespace App\Notifications;

use App\Models\MonthlyReport;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;

/**
 * Kelompok terlambat melapor (H+1 tenggat). Dikirim ke akun kelompok itu dan ke peninjau (OPD terkait,
 * atau Admin bila kelompok tanpa OPD pembina); kalimatnya disusun App\Support\NotificationText.
 */
class ReportOverdue extends Notification
{
    public function __construct(private Organization $organization, private CarbonImmutable $period) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'report_overdue',
            'stage' => 'terlambat',
            // Kalimat untuk kelompok berbeda dengan kalimat untuk OPD/Admin.
            'audience' => $notifiable->isKelompok() ? 'kelompok' : 'reviewer',
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'period' => $this->period->format('Y-m'),
            'deadline' => MonthlyReport::deadlineFor($this->period)->toDateString(),
        ];
    }
}
