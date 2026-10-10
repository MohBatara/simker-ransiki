<?php

namespace App\Support\Dashboards;

use App\Enums\LegalStatus;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Isi dasbor Kelompok: laporan bulan wajib lapor, bulan berjalan, catatan revisi terbaru,
 * kelengkapan profil, dan riwayat enam bulan.
 */
class KelompokSummary
{
    /** @var Collection<string, MonthlyReport> laporan per "Y-m" */
    private Collection $reports;

    public function __construct(private Organization $organization)
    {
        $this->reports = $organization->monthlyReports()->get()->keyBy(fn (MonthlyReport $report) => $report->period->format('Y-m'));
    }

    /** null bila kewajiban lapor kelompok ini baru dimulai setelah bulan wajib lapor. */
    public function due(): ?array
    {
        $period = ReportingPeriod::due();

        if (! $this->organization->acceptsReportFor($period)) {
            return null;
        }

        $report = $this->reports->get($period->format('Y-m'));
        $deadline = MonthlyReport::deadlineFor($period);

        return [
            'period' => $period,
            'report' => $report,
            'deadline' => $deadline,
            'distance' => ReportingPeriod::deadlineDistance($deadline),
            'daysLeft' => ReportingPeriod::daysUntil($deadline),
            'pastDeadline' => MonthlyReport::isPastDeadline($period),
            // Bulan sebelumnya yang belum pernah diajukan (belum dibuat atau masih draf).
            'earlierOutstanding' => $this->organization->reportingPeriods()
                ->filter(fn (CarbonImmutable $earlier) => $earlier->lt($period))
                ->filter(fn (CarbonImmutable $earlier) => $this->reports->get($earlier->format('Y-m'))?->submitted_at === null)
                ->count(),
        ];
    }

    /** Laporan bulan berjalan (bisa diisi sebagai draf), atau null bila belum dibuat. */
    public function current(): array
    {
        $period = now()->toImmutable()->startOfMonth();

        return [
            'period' => $period,
            'report' => $this->reports->get($period->format('Y-m')),
            'allowed' => $this->organization->acceptsReportFor($period),
        ];
    }

    /** Laporan berstatus perlu revisi yang terbaru, dengan catatannya. */
    public function latestRevision(): ?MonthlyReport
    {
        return $this->reports->where('status', ReportStatus::Revision)->sortByDesc('period')->first()?->load('latestRevisionRequest.user.opd');
    }

    /** @return list<array{label: string, done: bool, route: string}> */
    public function profileChecklist(): array
    {
        $products = $this->organization->products()->toBase()
            ->selectRaw('COUNT(*) as total, COUNT(photo_path) as with_photo')
            ->first();

        return [
            ['label' => 'Deskripsi kelompok', 'done' => filled($this->organization->description), 'route' => 'kelompok.profile.edit'],
            ['label' => 'Minimal satu produk', 'done' => $products->total > 0, 'route' => 'kelompok.products.index'],
            ['label' => 'Foto produk', 'done' => $products->with_photo > 0, 'route' => 'kelompok.products.index'],
            ['label' => 'Status legalitas', 'done' => $this->organization->legal_status !== LegalStatus::Unknown, 'route' => 'kelompok.licenses.index'],
        ];
    }

    /**
     * Enam bulan terakhir sampai bulan wajib lapor, terbaru dulu; bulan yang belum dibuat ikut tampil.
     *
     * @return Collection<int, array{period: CarbonImmutable, report: ?MonthlyReport}>
     */
    public function history(int $months = 6): Collection
    {
        $due = ReportingPeriod::due();

        return $this->organization->reportingPeriods()
            ->filter(fn (CarbonImmutable $period) => $period->lte($due))
            ->take($months)
            ->map(fn (CarbonImmutable $period) => ['period' => $period, 'report' => $this->reports->get($period->format('Y-m'))])
            ->values();
    }
}
