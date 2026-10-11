<?php

namespace App\Actions\Issues;

use App\Enums\FollowUpStatus;
use App\Enums\Role;
use App\Models\ReportIssue;
use App\Models\User;
use App\Notifications\IssueFollowUpUpdated;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Mencatat tindak lanjut satu masalah/kebutuhan: baris riwayat (issue_follow_ups) + status terkini di
 * report_issues. Bila statusnya berubah, semua akun aktif kelompok diberi notifikasi (sekali per perubahan).
 */
class FollowUpIssue
{
    public function handle(User $user, ReportIssue $issue, FollowUpStatus $status, string $note, CarbonInterface $actedOn, ?int $opdId): ReportIssue
    {
        $changed = false;

        $issue = DB::transaction(function () use ($user, $issue, $status, $note, $actedOn, $opdId, &$changed) {
            $issue = ReportIssue::lockForUpdate()->findOrFail($issue->id);
            Gate::forUser($user)->authorize('followUp', $issue);

            // OPD selalu bertanggung jawab atas namanya sendiri; Admin boleh memilih OPD.
            $opdId = $user->isOpd() ? $user->opd_id : $opdId;
            $changed = $issue->follow_up_status !== $status;

            $issue->followUps()->create([
                'user_id' => $user->id,
                'opd_id' => $opdId,
                'from_status' => $issue->follow_up_status,
                'to_status' => $status,
                'note' => $note,
                'acted_on' => $actedOn->format('Y-m-d'),
            ]);

            $issue->forceFill([
                'follow_up_status' => $status,
                'follow_up_opd_id' => $opdId,
                'follow_up_at' => $changed ? now() : $issue->follow_up_at,
            ])->save();

            return $issue;
        });

        if ($changed) {
            $issue->load('monthlyReport.organization');

            Notification::send(
                $issue->monthlyReport->organization->users()->where('role', Role::Kelompok)->where('is_active', true)->get(),
                new IssueFollowUpUpdated($issue, $note),
            );
        }

        return $issue;
    }
}
