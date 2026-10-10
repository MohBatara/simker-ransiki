<?php

namespace App\Notifications;

use App\Models\MonthlyReport;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;

/**
 * Pengingat bulanan untuk akun kelompok yang belum mengajukan laporan. Tahap "awal" (laporan sudah
 * bisa diajukan) dan "akhir" (tenggat dekat); kalimatnya disusun App\Support\NotificationText.
 */
class ReportReminder extends Notification
{
    public function __construct(private Organization $organization, private CarbonImmutable $period, private string $stage) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'report_reminder',
            'stage' => $this->stage,
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'period' => $this->period->format('Y-m'),
            'deadline' => MonthlyReport::deadlineFor($this->period)->toDateString(),
            'sent_on' => now()->toDateString(),
        ];
    }
}
