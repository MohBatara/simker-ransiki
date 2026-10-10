<?php

namespace App\Support\Dashboards;

use App\Enums\IssueKind;
use App\Enums\ProblemCategory;
use App\Enums\ReportStatus;
use App\Enums\SupportCategory;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\ReportIssue;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Angka pelaporan untuk dasbor OPD dan Admin, dibatasi kelompok yang terlihat pengguna
 * (Organization::visibleTo). Kelompok dihitung unik; satu kelompok punya paling banyak satu laporan
 * per periode, sehingga setiap kelompok masuk tepat satu kelompok status.
 */
class ReportingStats
{
    /** Urutan segmen bilah: status laporan, lalu "belum melapor" (belum ada baris laporan). */
    public const BUCKETS = ['verified', 'submitted', 'revision', 'draft', 'missing'];

    public function __construct(private User $user, private CarbonImmutable $period) {}

    /** @return array{verified: int, submitted: int, revision: int, draft: int, missing: int, total: int} */
    public function progress(): array
    {
        return $this->bucketCounts($this->obligated()->get()->pluck('total', 'bucket'));
    }

    /**
     * Progres per OPD (Admin). Kelompok dengan beberapa OPD dihitung di setiap OPD-nya; baris terakhir
     * berisi kelompok tanpa OPD pembina.
     *
     * @return Collection<int, array{opd: ?Opd, counts: array}>
     */
    public function progressPerOpd(): Collection
    {
        $rows = $this->obligated()
            ->join('organization_opd', 'organization_opd.organization_id', '=', 'organizations.id')
            ->addSelect('organization_opd.opd_id')
            ->groupBy('organization_opd.opd_id')
            ->get()
            ->groupBy('opd_id');

        $perOpd = Opd::orderBy('name')->get()->map(fn (Opd $opd) => [
            'opd' => $opd,
            'counts' => $this->bucketCounts(($rows[$opd->id] ?? collect())->pluck('total', 'bucket')),
        ]);

        $withoutOpd = $this->bucketCounts($this->obligated()->doesntHave('opds')->get()->pluck('total', 'bucket'));

        return $perOpd->push(['opd' => null, 'counts' => $withoutOpd]);
    }

    /**
     * Kelompok wajib lapor yang belum mengajukan laporan periode ini (belum ada laporan, atau masih draf).
     *
     * @return Collection<int, array{organization: Organization, report: ?MonthlyReport}>
     */
    public function notSubmitted(int $limit = 10): Collection
    {
        return Organization::visibleTo($this->user)
            ->obligatedFor($this->period)
            ->whereDoesntHave('monthlyReports', fn ($query) => $query
                ->whereDate('period', $this->period->format('Y-m-d'))
                ->where('status', '!=', ReportStatus::Draft))
            ->with(['monthlyReports' => fn ($query) => $query->whereDate('period', $this->period->format('Y-m-d'))])
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'leader_name', 'phone'])
            ->map(fn (Organization $organization) => ['organization' => $organization, 'report' => $organization->monthlyReports->first()]);
    }

    /**
     * Kategori masalah dan kebutuhan terbanyak periode ini, dihitung per kelompok unik, hanya dari
     * laporan diajukan atau terverifikasi.
     *
     * @return array{problem: Collection, support: Collection}
     */
    public function topIssues(int $limit = 5): array
    {
        $rows = ReportIssue::reviewableBy($this->user)
            ->join('monthly_reports', 'monthly_reports.id', '=', 'report_issues.monthly_report_id')
            ->whereDate('monthly_reports.period', $this->period->format('Y-m-d'))
            ->whereNotNull('report_issues.category')
            ->groupBy('report_issues.kind', 'report_issues.category')
            ->select('report_issues.kind', 'report_issues.category', DB::raw('COUNT(DISTINCT monthly_reports.organization_id) as organizations'))
            ->orderByDesc('organizations')
            ->orderBy('report_issues.category')
            ->get();

        $top = fn (IssueKind $kind, string $enum) => $rows->where('kind', $kind)->take($limit)->map(fn (ReportIssue $row) => [
            'kind' => $kind->value,
            'category' => $row->category,
            'label' => $enum::tryFrom($row->category)?->label() ?? $row->category,
            'organizations' => (int) $row->organizations,
        ])->values();

        return [
            'problem' => $top(IssueKind::Problem, ProblemCategory::class),
            'support' => $top(IssueKind::Support, SupportCategory::class),
        ];
    }

    /**
     * Laporan terverifikasi per bulan untuk N bulan sampai periode terpilih, dibandingkan dengan
     * jumlah kelompok wajib lapor bulan itu (status kelompok saat ini).
     *
     * @return Collection<int, array{period: CarbonImmutable, verified: int, obligated: int}>
     */
    public function verifiedTrend(int $months = 6): Collection
    {
        $first = $this->period->subMonthsNoOverflow($months - 1);

        $verified = MonthlyReport::visibleTo($this->user)
            ->where('status', ReportStatus::Verified)
            ->whereBetween('period', [$first->format('Y-m-d'), $this->period->format('Y-m-d')])
            ->groupBy('period')
            ->selectRaw('period, COUNT(DISTINCT organization_id) as total')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->period->format('Y-m') => (int) $row->total]);

        $starts = Organization::visibleTo($this->user)
            ->obligatedFor($this->period)
            ->pluck('reporting_starts_on');

        return collect(range(0, $months - 1))->map(function (int $offset) use ($first, $verified, $starts) {
            $period = $first->addMonthsNoOverflow($offset);

            return [
                'period' => $period,
                'verified' => $verified[$period->format('Y-m')] ?? 0,
                'obligated' => $starts->filter(fn ($start) => $start->lte($period))->count(),
            ];
        });
    }

    /**
     * Segmen untuk x-stacked-bar; setiap angka menuju daftar yang sudah tersaring.
     *
     * @param  array  $extra  filter tambahan untuk tautan, mis. ['opd' => 3] di tabel per OPD
     */
    public static function segments(array $counts, string $area, CarbonImmutable $period, array $extra = []): array
    {
        $month = $period->format('Y-m');
        $reports = fn (string $status) => route($area.'.reports.index', [...$extra, 'status' => $status, 'period' => $month]);

        return [
            ['label' => 'Terverifikasi', 'value' => $counts['verified'], 'tone' => 'ok', 'href' => $reports('verified')],
            ['label' => 'Menunggu', 'value' => $counts['submitted'], 'tone' => 'wait', 'href' => $reports('submitted')],
            ['label' => 'Perlu revisi', 'value' => $counts['revision'], 'tone' => 'fix', 'href' => $reports('revision')],
            ['label' => 'Draf', 'value' => $counts['draft'], 'tone' => 'idle', 'href' => $reports('draft')],
            ['label' => 'Belum melapor', 'value' => $counts['missing'], 'tone' => 'empty',
                'href' => route($area.'.organizations.index', [...$extra, 'laporan' => 'belum-dibuat', 'periode' => $month])],
        ];
    }

    /** Kelompok wajib lapor + status laporannya periode ini, dikelompokkan per status. */
    private function obligated(): Builder
    {
        return Organization::visibleTo($this->user)
            ->obligatedFor($this->period)
            ->leftJoin('monthly_reports', fn ($join) => $join
                ->on('monthly_reports.organization_id', '=', 'organizations.id')
                ->whereDate('monthly_reports.period', $this->period->format('Y-m-d')))
            ->select(DB::raw("COALESCE(monthly_reports.status, 'missing') as bucket"), DB::raw('COUNT(DISTINCT organizations.id) as total'))
            ->groupBy('bucket');
    }

    private function bucketCounts(Collection $totals): array
    {
        $counts = collect(self::BUCKETS)->mapWithKeys(fn (string $bucket) => [$bucket => (int) ($totals[$bucket] ?? 0)])->all();

        return [...$counts, 'total' => array_sum($counts)];
    }
}
