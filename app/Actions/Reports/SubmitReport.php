<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Enums\Role;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Notifications\ReportSubmitted;
use App\Support\ReportForm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Mengajukan laporan (dari draf atau revisi). Kelengkapan dicek dari data yang sudah tersimpan.
 */
class SubmitReport
{
    public function handle(MonthlyReport $report, User $user): MonthlyReport
    {
        $resubmitted = false;

        $report = DB::transaction(function () use ($report, $user, &$resubmitted) {
            $report = MonthlyReport::lockForUpdate()->findOrFail($report->id);
            Gate::forUser($user)->authorize('submit', $report);

            $this->validate($report);
            $resubmitted = $report->reviews()->whereIn('action', [ReviewAction::RevisionRequested, ReviewAction::Reopened])->exists();

            // submitted_at menyimpan pengajuan pertama (dasar status terlambat); pengajuan ulang
            // setelah revisi hanya tercatat di riwayat.
            $report->forceFill([
                'status' => ReportStatus::Submitted,
                'submitted_at' => $report->submitted_at ?? now(),
                'submitted_by' => $report->submitted_by ?? $user->id,
            ])->save();

            $report->reviews()->create(['user_id' => $user->id, 'action' => ReviewAction::Submitted]);

            return $report;
        });

        Notification::send($this->reviewers($report), new ReportSubmitted($report, $resubmitted));

        return $report;
    }

    private function validate(MonthlyReport $report): void
    {
        $required = Rule::requiredIf(! $report->no_activity);

        Validator::make(ReportForm::storedData($report), [
            'activity_summary' => [$required],
            'members_active' => [$required],
            'items.*.product_name' => ['required'],
            'items.*.production_unit' => ['required_with:items.*.production_qty'],
            'items.*.sales_unit' => ['required_with:items.*.sales_qty'],
            'problems.*.category' => ['required'],
            'problems.*.description' => ['required_if:problems.*.category,lainnya'],
            'supports.*.category' => ['required'],
            'supports.*.description' => ['required_if:supports.*.category,lainnya'],
        ], [
            'activity_summary.required' => 'Isi ringkasan aktivitas, atau pilih "Tidak ada aktivitas bulan ini".',
            'members_active.required' => 'Isi jumlah anggota aktif. Isi 0 bila memang tidak ada.',
            'items.*.product_name.required' => 'Pilih produk atau tulis nama produknya.',
            'items.*.production_unit.required_with' => 'Isi satuan produksi, misalnya kg.',
            'items.*.sales_unit.required_with' => 'Isi satuan penjualan, misalnya kg.',
            'problems.*.category.required' => 'Pilih kategori masalah.',
            'problems.*.description.required_if' => 'Jelaskan masalah dengan kategori "Lainnya".',
            'supports.*.category.required' => 'Pilih kategori kebutuhan.',
            'supports.*.description.required_if' => 'Jelaskan kebutuhan dengan kategori "Lainnya".',
        ])->validate();
    }

    /**
     * Akun aktif yang bisa memverifikasi: OPD pembina, atau semua Admin bila tidak ada petugas OPD yang boleh
     * memverifikasinya (kelompok tanpa OPD pembina, atau dengan pemisahan tugas semua petugasnya ikut mengisi).
     */
    private function reviewers(MonthlyReport $report)
    {
        $opdIds = $report->organization->opds()->pluck('opds.id');
        $eligible = fn ($users) => MonthlyReport::separatesDuties()
            ? $users->reject(fn (User $user) => $report->wasHandledBy($user))->values()
            : $users;

        $opdUsers = $opdIds->isEmpty()
            ? collect()
            : $eligible(User::query()->where('is_active', true)->where('role', Role::Opd)->whereIn('opd_id', $opdIds)->get());

        return $opdUsers->isNotEmpty()
            ? $opdUsers
            : $eligible(User::query()->where('is_active', true)->where('role', Role::Admin)->get());
    }
}
