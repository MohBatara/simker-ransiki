<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Enums\Role;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Notifications\ReportReviewed;
use App\Notifications\ReportSubmitted;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Tindakan peninjau atas laporan: verifikasi, minta revisi (Admin/OPD pembina), dan buka kembali
 * laporan terverifikasi (khusus Admin). Setiap tindakan tercatat di report_reviews.
 */
class ReviewReport
{
    public function verify(MonthlyReport $report, User $user, ?string $note): MonthlyReport
    {
        return $this->apply($report, $user, 'verify', ReportStatus::Submitted, ReviewAction::Verified, $note, fn (MonthlyReport $report) => [
            'status' => ReportStatus::Verified,
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);
    }

    public function requestRevision(MonthlyReport $report, User $user, string $note): MonthlyReport
    {
        return $this->apply($report, $user, 'requestRevision', ReportStatus::Submitted, ReviewAction::RevisionRequested, $note, fn () => [
            'status' => ReportStatus::Revision,
        ]);
    }

    /** Verifikasi dicabut; riwayat tetap menyimpan siapa yang dulu memverifikasi. */
    public function reopen(MonthlyReport $report, User $user, string $reason): MonthlyReport
    {
        return $this->apply($report, $user, 'reopen', ReportStatus::Verified, ReviewAction::Reopened, $reason, fn () => [
            'status' => ReportStatus::Revision,
            'verified_at' => null,
            'verified_by' => null,
        ]);
    }

    private function apply(MonthlyReport $report, User $user, string $ability, ReportStatus $expected, ReviewAction $action, ?string $note, callable $changes): MonthlyReport
    {
        $report = DB::transaction(function () use ($report, $user, $ability, $expected, $action, $note, $changes) {
            $report = MonthlyReport::lockForUpdate()->findOrFail($report->id);

            // Hak lihat dulu (404 bagi yang tidak berhak), baru status, baru hak tindakannya.
            Gate::forUser($user)->authorize('view', $report);

            // Peninjau lain bisa bertindak lebih dulu sejak halaman dibuka.
            if ($report->status !== $expected) {
                $last = $report->reviews()->with('user')->latest('id')->first();

                throw ValidationException::withMessages([
                    'report' => 'Laporan ini sudah ditinjau'.($last?->user ? ' oleh '.$last->user->name : '').'. Status sekarang: '.$report->status->label().'.',
                ]);
            }

            Gate::forUser($user)->authorize($ability, $report);

            $report->forceFill($changes($report))->save();
            $report->reviews()->create(['user_id' => $user->id, 'action' => $action, 'note' => filled($note) ? $note : null]);

            return $report;
        });

        // Pekerjaan sudah selesai: "menunggu verifikasi" ditandai dibaca bagi semua peninjau.
        DatabaseNotification::query()
            ->where('type', ReportSubmitted::class)
            ->whereNull('read_at')
            ->where('data->monthly_report_id', $report->id)
            ->update(['read_at' => now()]);

        Notification::send(
            $report->organization->users()->where('role', Role::Kelompok)->where('is_active', true)->get(),
            new ReportReviewed($report, $action, $note),
        );
        Notification::send($this->otherReviewers($report, $user), new ReportReviewed($report, $action, $note, $user->loadMissing('opd')));

        return $report;
    }

    /**
     * OPD pembina dan akun OPD/Admin yang mengisi atau mengajukan laporan atas nama kelompok (mis. kelompok tanpa
     * akun), kecuali peninjau yang bertindak. Mereka perlu tahu hasilnya, terutama bila laporan diminta revisi.
     */
    private function otherReviewers(MonthlyReport $report, User $actor)
    {
        $handlers = $report->reviews()->whereIn('action', ReviewAction::handling())->pluck('user_id')
            ->push($report->entered_by)->filter()->unique();

        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($actor->id)
            ->where('role', '!=', Role::Kelompok)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('role', Role::Opd)->whereIn('opd_id', $report->organization->opds()->select('opds.id')))
                ->orWhereIn('id', $handlers))
            ->get();
    }
}
