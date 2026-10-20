<?php

namespace App\Support\Dashboards;

use App\Enums\LegalStatus;
use App\Enums\ReportingState;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\ReportIssue;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Isi dasbor Kelompok: satu tugas utama di kartu sapaan (task), laporan bulan wajib lapor, bulan berjalan,
 * catatan revisi terbaru, kelengkapan profil, dan riwayat beberapa bulan terakhir.
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
            'daysLate' => max(1, -ReportingPeriod::daysUntil($deadline)),
            // Bulan sebelumnya yang belum pernah diajukan (belum dibuat atau masih draf).
            'earlierOutstanding' => $this->organization->reportingPeriods()
                ->filter(fn (CarbonImmutable $earlier) => $earlier->lt($period))
                ->filter(fn (CarbonImmutable $earlier) => $this->reports->get($earlier->format('Y-m'))?->submitted_at === null)
                ->count(),
        ];
    }

    /**
     * Satu tugas utama untuk kartu sapaan, yang pertama berlaku: perlu revisi, terlambat (belum pernah diajukan dan lewat
     * tenggat), belum diajukan sebelum tenggat, menunggu verifikasi, terverifikasi. "Terlambat" mengikuti ReportingState:
     * laporan yang sudah diajukan tepat waktu lalu diminta revisi tidak pernah terlambat.
     *
     * @return array{kind: string, tone: string, title: string, period: ?CarbonImmutable, report: ?MonthlyReport, action: ?string, note: ?array{text: string, by: ?string, date: ?CarbonInterface}}
     */
    public function task(): array
    {
        $task = fn (string $kind, string $title, ?CarbonImmutable $period = null, ?MonthlyReport $report = null, ?string $action = null, ?array $note = null) => [
            'kind' => $kind,
            'tone' => in_array($kind, ['revision', 'overdue'], true) ? 'danger' : 'brand',
            'title' => $title,
            'period' => $period,
            'report' => $report,
            'action' => $action,
            'note' => $note,
        ];

        if ($revision = $this->latestRevision()) {
            $request = $revision->latestRevisionRequest;

            return $task('revision', 'Laporan '.Format::month($revision->period).' perlu diperbaiki', $revision->period->toImmutable(), $revision, 'Perbaiki laporan', $request ? [
                'text' => $request->note,
                'by' => $request->user?->nameWithAgency(),
                'date' => $request->created_at,
            ] : null);
        }

        $due = $this->due();

        if (! $due) {
            return $task('not-started', 'Kewajiban lapor dimulai '.Format::month($this->organization->reporting_starts_on).'.');
        }

        $period = $due['period'];
        $report = $due['report'];
        $month = Format::month($period);

        return match (ReportingState::for($report, $period)) {
            ReportingState::Overdue => $task('overdue', 'Laporan '.$month.' terlambat '.$due['daysLate'].' hari', $period, $report, 'Isi laporan sekarang'),
            ReportingState::Draft, ReportingState::Missing => $task('open', 'Laporan '.$month.' paling lambat '.Format::date($due['deadline']).' ('.$due['distance'].')', $period, $report, 'Isi laporan'),
            ReportingState::Submitted => $task('waiting', 'Laporan '.$month.' sedang diperiksa OPD', $period, $report),
            // Revision tanpa catatan sudah ditangani di atas; sisanya terverifikasi.
            default => $task('done', 'Laporan '.$month.' sudah terverifikasi. Terima kasih.', $period, $report),
        };
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
     * Beberapa bulan terakhir sampai bulan wajib lapor, terbaru dulu; bulan yang belum dibuat ikut tampil.
     *
     * @return Collection<int, array{period: CarbonImmutable, report: ?MonthlyReport, state: ReportingState}>
     */
    public function history(int $months = 6): Collection
    {
        $due = ReportingPeriod::due();

        return $this->organization->reportingPeriods()
            ->filter(fn (CarbonImmutable $period) => $period->lte($due))
            ->take($months)
            ->map(fn (CarbonImmutable $period) => [
                'period' => $period,
                'report' => $report = $this->reports->get($period->format('Y-m')),
                'state' => ReportingState::for($report, $period),
            ])
            ->values();
    }

    /**
     * Bulan wajib lapor yang belum pernah diajukan dan lebih lama dari $before (di luar daftar ringkas "Laporan saya").
     */
    public function outstandingBefore(CarbonImmutable $before): int
    {
        return $this->organization->reportingPeriods()
            ->filter(fn (CarbonImmutable $period) => $period->lt($before))
            ->filter(fn (CarbonImmutable $period) => $this->reports->get($period->format('Y-m'))?->submitted_at === null)
            ->count();
    }

    /**
     * Masalah & kebutuhan dari laporan yang sudah diajukan, yang paling baru ditindaklanjuti dulu.
     *
     * @return Collection<int, ReportIssue>
     */
    public function followUps(int $limit = 5): Collection
    {
        return ReportIssue::query()
            ->whereHas('monthlyReport', fn ($query) => $query->where('organization_id', $this->organization->id)->whereNotNull('submitted_at'))
            ->with(['monthlyReport:id,period,status', 'followUps' => fn ($query) => $query->select(['id', 'report_issue_id', 'note'])])
            ->orderByRaw('follow_up_at IS NULL')
            ->orderByDesc('follow_up_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
