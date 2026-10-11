<?php

namespace App\Notifications;

use App\Models\ReportIssue;
use App\Support\NotificationText;
use Illuminate\Notifications\Notification;

/**
 * Untuk akun kelompok saat status tindak lanjut masalah/kebutuhan dari laporannya berubah.
 */
class IssueFollowUpUpdated extends Notification
{
    public function __construct(private ReportIssue $issue, private string $note) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'issue_follow_up',
            ...NotificationText::reportData($this->issue->monthlyReport),
            'report_issue_id' => $this->issue->id,
            'issue_kind' => $this->issue->kind->value,
            'category' => $this->issue->categoryLabel(),
            'status' => $this->issue->follow_up_status->value,
            'note' => $this->note,
        ];
    }
}
