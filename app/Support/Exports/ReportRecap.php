<?php

namespace App\Support\Exports;

use App\Enums\ReportingState;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\ReportItem;
use App\Models\User;
use App\Support\Dashboards\ReportingPeriod;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rekap laporan satu periode untuk Admin dan OPD, dibatasi kelompok yang terlihat pengguna
 * (Organization::visibleTo). Status pelaporan memakai ReportingState (termasuk "Terlambat"); semua angka
 * hanya dari laporan terverifikasi, NULL tidak dihitung sebagai 0, dan produksi dikelompokkan per produk +
 * satuan (tidak pernah dijumlah lintas satuan).
 */
class ReportRecap
{
    /** Kolom angka laporan => label. */
    public const NUMBERS = [
        'activity_count' => 'Jumlah kegiatan',
        'members_active' => 'Anggota aktif',
        'workforce_count' => 'Tenaga kerja',
        'members_change' => 'Perubahan anggota',
        'income_sales' => 'Pendapatan penjualan (Rp)',
        'income_other' => 'Pendapatan lain (Rp)',
        'costs' => 'Biaya (Rp)',
        'profit' => 'Laba (Rp)',
    ];

    private ?Collection $statusRows = null;

    public function __construct(public readonly User $user, public readonly CarbonImmutable $period) {}

    /** Pilihan bulan rekap, sama dengan pilihan bulan di dasbor (Y-m => "September 2026"). */
    public static function periodOptions(User $user): array
    {
        return ReportingPeriod::options(self::earliest($user));
    }

    /** Periode dari ?periode=Y-m; nilai tidak sah atau di luar rentang kembali ke bulan wajib lapor. */
    public static function resolvePeriod(User $user, mixed $input): CarbonImmutable
    {
        return ReportingPeriod::resolve($input, self::earliest($user));
    }

    private static function earliest(User $user): ?CarbonImmutable
    {
        $earliest = Organization::visibleTo($user)->min('reporting_starts_on');

        return $earliest ? CarbonImmutable::parse($earliest)->startOfMonth() : null;
    }

    /**
     * Kelompok wajib lapor periode ini beserta status pelaporannya, urut nama.
     *
     * @return Collection<int, array{organization: Organization, report: ?MonthlyReport, state: ReportingState}>
     */
    public function statusRows(): Collection
    {
        return $this->statusRows ??= Organization::visibleTo($this->user)
            ->obligatedFor($this->period)
            ->with([
                'institutionType:id,name', 'village:id,name', 'opds:id,name,short_name',
                'monthlyReports' => fn ($query) => $query->whereDate('period', $this->period->format('Y-m-d')),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization) => [
                'organization' => $organization,
                'report' => $report = $organization->monthlyReports->first(),
                'state' => ReportingState::for($report, $this->period),
            ]);
    }

    /** @return array<string, int> label status => jumlah kelompok, termasuk status yang nol */
    public function stateCounts(): array
    {
        $counts = $this->statusRows()->countBy(fn (array $row) => $row['state']->value);

        return collect(ReportingState::cases())
            ->mapWithKeys(fn (ReportingState $state) => [$state->label() => (int) ($counts[$state->value] ?? 0)])
            ->all();
    }

    /**
     * Jumlah setiap angka dari laporan terverifikasi dan berapa kelompok yang mengisinya.
     *
     * @return array{reports: int, totals: array<string, array{label: string, sum: ?int, filled: int}>}
     */
    public function economy(): array
    {
        $row = $this->verifiedReports()
            ->selectRaw('COUNT(DISTINCT monthly_reports.organization_id) as reports')
            ->addSelect(collect(array_keys(self::NUMBERS))->flatMap(fn (string $field) => [
                DB::raw("SUM(monthly_reports.{$field}) as {$field}_sum"),
                DB::raw("COUNT(monthly_reports.{$field}) as {$field}_filled"),
            ])->all())
            ->toBase()
            ->first();

        return [
            'reports' => (int) $row->reports,
            'totals' => collect(self::NUMBERS)->mapWithKeys(fn (string $label, string $field) => [$field => [
                'label' => $label,
                'sum' => $row->{$field.'_filled'} > 0 ? (int) $row->{$field.'_sum'} : null,
                'filled' => (int) $row->{$field.'_filled'},
            ]])->all(),
        ];
    }

    /**
     * Produksi per produk + satuan produksi.
     *
     * @return Collection<int, array{product: string, unit: ?string, quantity: ?float, filled: int, organizations: int}>
     */
    public function production(): Collection
    {
        return $this->itemTotals('production_qty', 'production_unit')
            ->map(fn ($row) => [
                'product' => $row->product,
                'unit' => $row->unit,
                'quantity' => $row->filled > 0 ? (float) $row->quantity : null,
                'filled' => (int) $row->filled,
                'organizations' => (int) $row->organizations,
            ]);
    }

    /**
     * Penjualan per produk + satuan jual, dengan pendapatan.
     *
     * @return Collection<int, array{product: string, unit: ?string, quantity: ?float, revenue: ?int, filled: int, organizations: int}>
     */
    public function sales(): Collection
    {
        return $this->itemTotals('sales_qty', 'sales_unit', withRevenue: true)
            ->map(fn ($row) => [
                'product' => $row->product,
                'unit' => $row->unit,
                'quantity' => $row->filled > 0 ? (float) $row->quantity : null,
                'revenue' => $row->revenue_filled > 0 ? (int) $row->revenue : null,
                'filled' => (int) $row->filled,
                'organizations' => (int) $row->organizations,
            ]);
    }

    /** Keterangan jumlah pengisi: "diisi 18 dari 25 kelompok". */
    public static function filledNote(int $filled, int $of): string
    {
        return "diisi {$filled} dari {$of} kelompok";
    }

    public function filenameBase(): string
    {
        return 'rekap-laporan-'.$this->period->format('Y-m');
    }

    /** Lembar Excel: Ringkasan, Status laporan, Produksi, Penjualan. */
    public function sheets(string $printedLine): array
    {
        $economy = $this->economy();
        $obligated = $this->statusRows()->count();
        $verified = $economy['reports'];

        $summary = [
            ['Rekap laporan bulanan'],
            ['Periode', Format::month($this->period)],
            [$printedLine],
            [],
            ['Status pelaporan', 'Jumlah kelompok'],
            ...collect($this->stateCounts())->map(fn (int $total, string $label) => [$label, $total])->values(),
            ['Total kelompok wajib lapor', $obligated],
            [],
            ['Angka dari laporan terverifikasi', 'Jumlah', 'Keterangan'],
            ['Laporan terverifikasi', $verified, "dari {$obligated} kelompok wajib lapor"],
            ...collect($economy['totals'])->map(fn (array $total) => [$total['label'], $total['sum'], self::filledNote($total['filled'], $verified)])->values(),
            [],
            ['Angka resmi hanya dari laporan terverifikasi.'],
        ];

        $date = fn ($value) => $value ? Format::date($value) : null;

        return [
            ['name' => 'Ringkasan', 'rows' => $summary],
            [
                'name' => 'Status laporan',
                'header' => ['Nama kelompok', 'Jenis kelembagaan', 'Kampung', 'OPD terkait', 'Status pelaporan', 'Diajukan', 'Diverifikasi', 'Keterangan'],
                'rows' => $this->statusRows()->map(fn (array $row) => [
                    $row['organization']->name,
                    $row['organization']->institutionType?->name,
                    $row['organization']->village?->name,
                    self::opdNames($row['organization']),
                    $row['state']->label(),
                    $date($row['report']?->submitted_at),
                    $date($row['report']?->verified_at),
                    $row['report']?->wasSubmittedLate() ? 'Diajukan terlambat' : null,
                ]),
            ],
            [
                'name' => 'Produksi',
                'header' => ['Produk', 'Satuan', 'Jumlah produksi', 'Keterangan'],
                'rows' => $this->production()->map(fn (array $row) => [
                    $row['product'], $row['unit'], $row['quantity'], self::filledNote($row['filled'], $row['organizations']),
                ]),
            ],
            [
                'name' => 'Penjualan',
                'header' => ['Produk', 'Satuan', 'Jumlah terjual', 'Pendapatan (Rp)', 'Keterangan'],
                'rows' => $this->sales()->map(fn (array $row) => [
                    $row['product'], $row['unit'], $row['quantity'], $row['revenue'], self::filledNote($row['filled'], $row['organizations']),
                ]),
            ],
        ];
    }

    public static function opdNames(Organization $organization): string
    {
        return $organization->opds->map(fn ($opd) => $opd->short_name ?: $opd->name)->sort()->join('; ');
    }

    private function verifiedReports()
    {
        return MonthlyReport::visibleTo($this->user)
            ->where('monthly_reports.status', ReportStatus::Verified)
            ->whereDate('monthly_reports.period', $this->period->format('Y-m-d'));
    }

    /** Nama produk dan satuan dibandingkan LOWER(TRIM(...)); baris tanpa jumlah maupun satuan diabaikan. */
    private function itemTotals(string $quantity, string $unit, bool $withRevenue = false): Collection
    {
        return ReportItem::query()
            ->join('monthly_reports', 'monthly_reports.id', '=', 'report_items.monthly_report_id')
            ->whereIn('monthly_reports.id', $this->verifiedReports()->select('monthly_reports.id'))
            ->whereNotNull('report_items.product_name')
            ->where(fn ($query) => $query
                ->whereNotNull("report_items.{$quantity}")
                ->orWhereNotNull("report_items.{$unit}")
                ->when($withRevenue, fn ($query) => $query->orWhereNotNull('report_items.revenue')))
            ->groupBy(DB::raw('LOWER(TRIM(report_items.product_name))'), DB::raw("LOWER(TRIM(report_items.{$unit}))"))
            ->select([
                DB::raw('MIN(TRIM(report_items.product_name)) as product'),
                DB::raw("LOWER(TRIM(report_items.{$unit})) as unit"),
                DB::raw("SUM(report_items.{$quantity}) as quantity"),
                DB::raw("COUNT(DISTINCT CASE WHEN report_items.{$quantity} IS NOT NULL THEN monthly_reports.organization_id END) as filled"),
                DB::raw('COUNT(DISTINCT monthly_reports.organization_id) as organizations'),
                DB::raw($withRevenue ? 'SUM(report_items.revenue) as revenue' : 'NULL as revenue'),
                DB::raw($withRevenue ? 'COUNT(report_items.revenue) as revenue_filled' : '0 as revenue_filled'),
            ])
            ->orderBy('product')
            ->orderBy('unit')
            ->toBase()
            ->get();
    }
}
