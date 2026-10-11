<?php

namespace App\Actions\Reports;

use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Membuat draf laporan kosong untuk satu bulan (oleh kelompok, atau OPD/Admin atas nama kelompok), agar
 * simpan otomatis dan unggah dokumentasi punya ID laporan. Bila bulan itu sudah ada laporan, laporan yang
 * ada dikembalikan.
 */
class CreateReport
{
    /** @return array{0: MonthlyReport, 1: bool} [laporan, baru dibuat] */
    public function handle(User $user, Organization $organization, CarbonInterface $period): array
    {
        Gate::forUser($user)->authorize('create', [MonthlyReport::class, $organization]);

        if (! $organization->acceptsReportFor($period)) {
            throw ValidationException::withMessages(['period' => 'Laporan hanya bisa dibuat untuk bulan sejak kewajiban lapor dimulai sampai bulan ini.']);
        }

        $existing = $organization->monthlyReports()->whereDate('period', $period)->first();
        if ($existing) {
            return [$existing, false];
        }

        try {
            return [$organization->monthlyReports()->create(['period' => $period, 'entered_by' => $user->id]), true];
        } catch (UniqueConstraintViolationException) {
            // Dua klik bersamaan: laporan sudah dibuat oleh permintaan lain.
            return [$organization->monthlyReports()->whereDate('period', $period)->firstOrFail(), false];
        }
    }
}
