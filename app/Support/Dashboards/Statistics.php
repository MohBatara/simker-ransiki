<?php

namespace App\Support\Dashboards;

use App\Enums\IssueKind;
use App\Enums\LicenseStatus;
use App\Enums\OrganizationStatus;
use App\Enums\ProblemCategory;
use App\Enums\ReportStatus;
use App\Enums\SupportCategory;
use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\ReportIssue;
use App\Models\ReportItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu pintu untuk semua angka halaman Statistik (Admin/OPD) dan grafik dasbor Kelompok.
 *
 * - Kelompok selalu dibatasi Organization::visibleTo + filter (jenis, kampung, bidang usaha, OPD khusus Admin),
 *   sehingga OPD tidak bisa melihat kelompok lain lewat parameter URL.
 * - Kelompok dihitung unik: filter memakai subquery/EXISTS, bukan join yang menggandakan baris.
 * - Angka ekonomi hanya dari laporan `verified`. NULL tidak dijumlahkan sebagai 0: setiap angka disertai
 *   jumlah yang mengisi, dan bulan tanpa isian bernilai null (celah di grafik).
 */
class Statistics
{
    /** Filter yang dipakai halaman Statistik; `opd` hanya untuk Admin. */
    public const FILTERS = ['type', 'village', 'sector', 'opd'];

    public const MAX_MONTHS = 36;

    public const EXPIRING_DAYS = 60;

    /** Status izin yang berarti kelompok "sudah punya/mengurus izin". */
    private const LICENSE_HELD = [LicenseStatus::Issued, LicenseStatus::InProcess, LicenseStatus::NeedsFix];

    private array $filters;

    public function __construct(
        private User $user,
        array $filters = [],
        private ?CarbonImmutable $from = null,
        private ?CarbonImmutable $to = null,
    ) {
        $allowed = $user->isAdmin() ? self::FILTERS : array_diff(self::FILTERS, ['opd']);
        $this->filters = array_filter(array_intersect_key($filters, array_flip($allowed)), fn ($value) => is_string($value) && $value !== '');
        $this->to ??= ReportingPeriod::due();
        $this->from ??= $this->to->subMonthsNoOverflow(11);
    }

    /**
     * Rentang ?dari=Y-m&sampai=Y-m. Nilai harus ada di pilihan bulan (dari kewajiban lapor paling awal sampai
     * bulan wajib lapor) dan dari ≤ sampai; selain itu kembali ke 12 bulan terakhir (dipotong di bulan paling awal).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function resolveRange(mixed $from, mixed $to, ?CarbonImmutable $earliest): array
    {
        $options = ReportingPeriod::options($earliest);
        $due = ReportingPeriod::due();
        $default = $due->subMonthsNoOverflow(11);
        $default = $earliest && $earliest->gt($default) ? $earliest : $default;

        $valid = fn (mixed $value) => is_string($value) && array_key_exists($value, $options);
        if (! $valid($from) || ! $valid($to)) {
            return [$default, $due];
        }

        $from = CarbonImmutable::createFromFormat('!Y-m', $from);
        $to = CarbonImmutable::createFromFormat('!Y-m', $to);

        if ($from->gt($to) || $from->diffInMonths($to) >= self::MAX_MONTHS) {
            return [$default, $due];
        }

        return [$from, $to];
    }

    /**
     * Kartu Ekonomi tab Ringkasan dasbor Admin/OPD (tanpa filter): penjualan, pendapatan lain, biaya, dan laba per bulan
     * untuk N bulan sampai periode terpilih, dipotong di kewajiban lapor paling awal. Baris terakhir = periode terpilih.
     *
     * @return Collection<int, array{period: CarbonImmutable, reports: int, obligated: int, provisional: bool, totals: array}>
     */
    public static function recentEconomy(User $user, CarbonImmutable $period, ?CarbonImmutable $earliest, int $months = 6): Collection
    {
        $from = $period->subMonthsNoOverflow($months - 1);
        $from = $earliest && $earliest->gt($from) && $earliest->lte($period) ? $earliest : $from;

        return (new self($user, [], $from, $period))->economyTrend();
    }

    /** Jumlah minimal kelompok yang sama di kedua bulan agar persen perubahan ditampilkan. */
    public const MIN_MATCHED = 5;

    /**
     * Perubahan persen dibanding bulan sebelumnya. Null bila salah satu jumlah kosong atau jumlah bulan sebelumnya nol
     * (persen tidak bermakna).
     */
    public static function change(?int $current, ?int $previous): ?int
    {
        if ($current === null || $previous === null || $previous === 0) {
            return null;
        }

        return (int) round(($current - $previous) / abs($previous) * 100);
    }

    /**
     * Perbandingan setara periode ini dengan bulan sebelumnya: hanya kelompok yang laporannya terverifikasi di KEDUA bulan
     * dan mengisi angka itu di keduanya (NULL tidak dihitung 0). Persen null bila kelompok yang sama < MIN_MATCHED.
     *
     * @return array<string, array{change: ?int, organizations: int, comparable: bool, previous: CarbonImmutable}>
     */
    public function matchedChange(CarbonImmutable $period): array
    {
        $previous = $period->subMonthNoOverflow();
        $fields = ['income_sales', 'income_other', 'costs', 'profit'];
        $both = fn (string $field) => "current.{$field} IS NOT NULL AND prior.{$field} IS NOT NULL";

        $row = DB::table('monthly_reports as current')
            ->join('monthly_reports as prior', function ($join) use ($previous) {
                $join->on('prior.organization_id', '=', 'current.organization_id')
                    ->where('prior.status', ReportStatus::Verified->value)
                    ->whereDate('prior.period', $previous->format('Y-m-d'));
            })
            ->where('current.status', ReportStatus::Verified->value)
            ->whereDate('current.period', $period->format('Y-m-d'))
            ->whereIn('current.organization_id', $this->organizations()->select('organizations.id'))
            ->selectRaw(collect($fields)->map(fn (string $field) => "SUM(CASE WHEN {$both($field)} THEN current.{$field} END) as {$field}_current, "
                ."SUM(CASE WHEN {$both($field)} THEN prior.{$field} END) as {$field}_prior, "
                ."COUNT(DISTINCT CASE WHEN {$both($field)} THEN current.organization_id END) as {$field}_organizations")->join(', '))
            ->first();

        return collect($fields)->mapWithKeys(function (string $field) use ($row, $previous) {
            $organizations = (int) ($row->{$field.'_organizations'} ?? 0);
            $comparable = $organizations >= self::MIN_MATCHED;

            return [$field => [
                'change' => $comparable ? self::change((int) $row->{$field.'_current'}, (int) $row->{$field.'_prior'}) : null,
                'organizations' => $organizations,
                'comparable' => $comparable,
                'previous' => $previous,
            ]];
        })->all();
    }

    public function filters(): array
    {
        return $this->filters;
    }

    public function from(): CarbonImmutable
    {
        return $this->from;
    }

    public function to(): CarbonImmutable
    {
        return $this->to;
    }

    /** @return Collection<int, CarbonImmutable> */
    public function months(): Collection
    {
        $months = collect();
        for ($month = $this->from; $month->lte($this->to); $month = $month->addMonthNoOverflow()) {
            $months->push($month);
        }

        return $months;
    }

    /** Kelompok yang terlihat pengguna dan cocok dengan filter. */
    public function organizations(): Builder
    {
        return Organization::visibleTo($this->user)->filter($this->filters);
    }

    /** Ringkasan untuk kartu: jumlah kelompok unik, laporan terverifikasi, dan total penjualan dalam rentang. */
    public function summary(): array
    {
        $row = $this->verifiedReports()->toBase()
            ->selectRaw('COUNT(*) as reports, COUNT(DISTINCT monthly_reports.organization_id) as organizations, SUM(monthly_reports.income_sales) as sales, COUNT(monthly_reports.income_sales) as sales_filled')
            ->first();

        return [
            'organizations' => $this->organizations()->count(),
            'reports' => (int) $row->reports,
            'reporting_organizations' => (int) $row->organizations,
            'sales' => $row->sales_filled > 0 ? (int) $row->sales : null,
            'sales_filled' => (int) $row->sales_filled,
        ];
    }

    /**
     * Pendapatan penjualan per bulan. `total` null bila tidak ada laporan terverifikasi yang mengisi, sehingga
     * garis terputus (bukan turun ke 0). `filled` dari `obligated` = cakupan "diisi X dari Y kelompok".
     * `provisional`: data bulan itu belum lengkap (lihat isProvisional).
     *
     * @return Collection<int, array{period: CarbonImmutable, total: ?int, filled: int, reports: int, obligated: int, provisional: bool}>
     */
    public function incomeTrend(): Collection
    {
        $rows = $this->verifiedReports()->toBase()
            ->groupBy('monthly_reports.period')
            ->selectRaw('monthly_reports.period as period, COUNT(*) as reports, SUM(monthly_reports.income_sales) as total, COUNT(monthly_reports.income_sales) as filled')
            ->get()
            ->keyBy(fn ($row) => substr($row->period, 0, 7));
        $obligated = $this->obligatedPerMonth();

        return $this->months()->map(function (CarbonImmutable $month) use ($rows, $obligated) {
            $row = $rows->get($month->format('Y-m'));

            $reports = (int) ($row->reports ?? 0);

            return [
                'period' => $month,
                'total' => $row && $row->filled > 0 ? (int) $row->total : null,
                'filled' => (int) ($row->filled ?? 0),
                'reports' => $reports,
                'obligated' => $obligated[$month->format('Y-m')],
                'provisional' => self::isProvisional($month, $reports, $obligated[$month->format('Y-m')]),
            ];
        });
    }

    /**
     * Angka ekonomi per bulan dari laporan terverifikasi (penjualan, pendapatan lain, biaya, laba). Untuk setiap angka:
     * jumlah (null bila tidak ada yang mengisi, bukan 0) dan berapa laporan yang mengisinya.
     *
     * @return Collection<int, array{period: CarbonImmutable, reports: int, obligated: int, provisional: bool, totals: array<string, array{sum: ?int, filled: int}>}>
     */
    public function economyTrend(): Collection
    {
        $fields = ['income_sales', 'income_other', 'costs', 'profit'];
        $rows = $this->verifiedReports()->toBase()
            ->groupBy('monthly_reports.period')
            ->selectRaw('monthly_reports.period as period, COUNT(*) as reports')
            ->addSelect(collect($fields)->flatMap(fn (string $field) => [
                DB::raw("SUM(monthly_reports.{$field}) as {$field}_sum"),
                DB::raw("COUNT(monthly_reports.{$field}) as {$field}_filled"),
            ])->all())
            ->get()
            ->keyBy(fn ($row) => substr($row->period, 0, 7));
        $obligated = $this->obligatedPerMonth();

        return $this->months()->map(function (CarbonImmutable $month) use ($rows, $obligated, $fields) {
            $row = $rows->get($month->format('Y-m'));
            $reports = (int) ($row->reports ?? 0);

            return [
                'period' => $month,
                'reports' => $reports,
                'obligated' => $obligated[$month->format('Y-m')],
                'provisional' => self::isProvisional($month, $reports, $obligated[$month->format('Y-m')]),
                'totals' => collect($fields)->mapWithKeys(fn (string $field) => [$field => [
                    'sum' => $row && $row->{$field.'_filled'} > 0 ? (int) $row->{$field.'_sum'} : null,
                    'filled' => (int) ($row->{$field.'_filled'} ?? 0),
                ]])->all(),
            ];
        });
    }

    /**
     * Bulan "sementara": belum lewat tenggat + N hari (config simker.statistics), atau laporan terverifikasi
     * kurang dari ambang cakupan kelompok wajib lapor. Bulan tanpa kelompok wajib lapor hanya memakai syarat waktu.
     */
    public static function isProvisional(CarbonImmutable $month, int $verified, int $obligated): bool
    {
        $settled = MonthlyReport::deadlineFor($month)->addDays(config('simker.statistics.provisional_days_after_deadline'));

        return now()->lte($settled)
            || ($obligated > 0 && $verified / $obligated < config('simker.statistics.provisional_min_coverage'));
    }

    /**
     * Status pelaporan per bulan untuk kelompok wajib lapor, mengikuti App\Enums\ReportingState: laporan yang
     * pernah diajukan masuk terverifikasi / perlu revisi / menunggu; sisanya terlambat bila tenggat sudah
     * lewat, selain itu "belum" (belum dibuat atau masih draf).
     *
     * @return Collection<int, array{period: CarbonImmutable, verified: int, submitted: int, revision: int, overdue: int, pending: int, obligated: int}>
     */
    public function compliance(): Collection
    {
        $rows = $this->reports()
            ->join('organizations', 'organizations.id', '=', 'monthly_reports.organization_id')
            ->whereIn('organizations.status', [OrganizationStatus::Active, OrganizationStatus::Inactive])
            ->whereColumn('organizations.reporting_starts_on', '<=', 'monthly_reports.period')
            ->whereBetween('monthly_reports.period', $this->rangeDates())
            ->whereNotNull('monthly_reports.submitted_at')
            ->toBase()
            ->groupBy('monthly_reports.period', 'bucket')
            ->selectRaw("monthly_reports.period as period, CASE monthly_reports.status WHEN 'verified' THEN 'verified' WHEN 'revision' THEN 'revision' ELSE 'submitted' END as bucket, COUNT(*) as total")
            ->get()
            ->groupBy(fn ($row) => substr($row->period, 0, 7));
        $obligated = $this->obligatedPerMonth();

        return $this->months()->map(function (CarbonImmutable $month) use ($rows, $obligated) {
            $counts = ($rows->get($month->format('Y-m')) ?? collect())->pluck('total', 'bucket');
            $total = $obligated[$month->format('Y-m')];
            $submitted = ['verified' => (int) ($counts['verified'] ?? 0), 'submitted' => (int) ($counts['submitted'] ?? 0), 'revision' => (int) ($counts['revision'] ?? 0)];
            $rest = max(0, $total - array_sum($submitted));
            $past = MonthlyReport::isPastDeadline($month);

            return ['period' => $month, ...$submitted, 'overdue' => $past ? $rest : 0, 'pending' => $past ? 0 : $rest, 'obligated' => $total];
        });
    }

    /**
     * Pendapatan penjualan per bidang usaha. Kelompok dengan beberapa bidang dihitung di setiap bidangnya,
     * sehingga angka antarbidang TIDAK boleh dijumlahkan.
     *
     * @return Collection<int, array{label: string, total: ?int, filled: int, organizations: int}>
     */
    public function incomeBySector(): Collection
    {
        return $this->verifiedReports()
            ->join('organization_sector', 'organization_sector.organization_id', '=', 'monthly_reports.organization_id')
            ->join('business_sectors', 'business_sectors.id', '=', 'organization_sector.business_sector_id')
            ->when((int) ($this->filters['sector'] ?? 0), fn ($query, $sector) => $query->where('business_sectors.id', $sector))
            ->toBase()
            ->groupBy('business_sectors.id', 'business_sectors.name')
            ->selectRaw('business_sectors.name as label, SUM(monthly_reports.income_sales) as total, COUNT(monthly_reports.income_sales) as filled, COUNT(DISTINCT monthly_reports.organization_id) as organizations')
            ->orderByDesc('total')
            ->orderBy('business_sectors.name')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => $row->filled > 0 ? (int) $row->total : null,
                'filled' => (int) $row->filled,
                'organizations' => (int) $row->organizations,
            ]);
    }

    /**
     * Produksi per produk + satuan (nama dan satuan dibandingkan LOWER(TRIM)); satuan berbeda tidak pernah
     * dijumlahkan. Urut dari jumlah terbesar.
     *
     * @return Collection<int, array{product: string, unit: ?string, quantity: ?string, filled: int, organizations: int}>
     */
    public function production(int $limit = 30): Collection
    {
        return $this->productionQuery()
            ->select([
                DB::raw('MIN(TRIM(report_items.product_name)) as product'),
                DB::raw('LOWER(TRIM(report_items.production_unit)) as unit'),
                DB::raw('SUM(report_items.production_qty) as quantity'),
                DB::raw('COUNT(report_items.production_qty) as filled'),
                DB::raw('COUNT(DISTINCT monthly_reports.organization_id) as organizations'),
            ])
            ->orderByDesc('quantity')
            ->orderBy('product')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product' => $row->product,
                'unit' => $row->unit,
                'quantity' => $row->filled > 0 ? $row->quantity : null,
                'filled' => (int) $row->filled,
                'organizations' => (int) $row->organizations,
            ]);
    }

    /**
     * Produksi per bulan untuk N produk + satuan terbesar (grafik dasbor Kelompok). Bulan tanpa isian = null.
     *
     * @return Collection<int, array{product: string, unit: ?string, values: list<?float>}>
     */
    public function productionTrend(int $series = 2): Collection
    {
        $rows = $this->productionQuery()
            ->groupBy('monthly_reports.period')
            ->select([
                'monthly_reports.period',
                DB::raw('LOWER(TRIM(report_items.product_name)) as product_key'),
                DB::raw('MIN(TRIM(report_items.product_name)) as product'),
                DB::raw('LOWER(TRIM(report_items.production_unit)) as unit'),
                DB::raw('SUM(report_items.production_qty) as quantity'),
                DB::raw('COUNT(report_items.production_qty) as filled'),
            ])
            ->get();

        return $rows->groupBy(fn ($row) => $row->product_key.'|'.$row->unit)
            ->sortByDesc(fn (Collection $group) => $group->sum(fn ($row) => (float) $row->quantity))
            ->take($series)
            ->map(function (Collection $group) {
                $byMonth = $group->keyBy(fn ($row) => substr($row->period, 0, 7));

                return [
                    'product' => $group->first()->product,
                    'unit' => $group->first()->unit,
                    'values' => $this->months()->map(function (CarbonImmutable $month) use ($byMonth) {
                        $row = $byMonth->get($month->format('Y-m'));

                        return $row && $row->filled > 0 ? (float) $row->quantity : null;
                    })->all(),
                ];
            })
            ->values();
    }

    /**
     * Kategori masalah/kebutuhan terbanyak dari laporan terverifikasi dalam rentang, dihitung per laporan
     * (satu laporan menyebut kategori yang sama dua kali tetap dihitung 1).
     *
     * @return array{problem: Collection, support: Collection}
     */
    public function issues(): array
    {
        $rows = ReportIssue::query()
            ->join('monthly_reports', 'monthly_reports.id', '=', 'report_issues.monthly_report_id')
            ->whereIn('monthly_reports.id', $this->verifiedReports()->select('monthly_reports.id'))
            ->whereNotNull('report_issues.category')
            ->toBase()
            ->groupBy('report_issues.kind', 'report_issues.category')
            ->selectRaw('report_issues.kind, report_issues.category, COUNT(DISTINCT monthly_reports.id) as reports, COUNT(DISTINCT monthly_reports.organization_id) as organizations')
            ->orderByDesc('reports')
            ->orderBy('report_issues.category')
            ->get();

        $map = fn (IssueKind $kind, string $enum) => $rows->where('kind', $kind->value)->map(fn ($row) => [
            'category' => $row->category,
            'label' => $enum::tryFrom($row->category)?->label() ?? $row->category,
            'reports' => (int) $row->reports,
            'organizations' => (int) $row->organizations,
        ])->values();

        return [
            'problem' => $map(IssueKind::Problem, ProblemCategory::class),
            'support' => $map(IssueKind::Support, SupportCategory::class),
        ];
    }

    /**
     * Kartu legalitas, dihitung per kelompok unik (EXISTS per kelompok, bukan menghitung baris izin).
     * Satu kelompok bisa masuk beberapa kartu (mis. satu izin terbit dan satu sedang diproses).
     *
     * @return array{total: int, issued: int, in_process: int, needs_fix: int, without: int}
     */
    public function legalSummary(): array
    {
        return $this->legalRow($this->legalCounts($this->organizations()->toBase()->selectRaw('COUNT(*) as total'))->first());
    }

    /**
     * Rincian legalitas per jenis kelembagaan atau per kampung.
     *
     * @param  'type'|'village'  $by
     * @return Collection<int, array{label: string, total: int, issued: int, in_process: int, needs_fix: int, without: int}>
     */
    public function legalBreakdown(string $by): Collection
    {
        [$table, $column] = $by === 'type' ? ['institution_types', 'institution_type_id'] : ['villages', 'village_id'];

        $query = $this->organizations()->toBase()
            ->join($table, "{$table}.id", '=', "organizations.{$column}")
            ->groupBy("{$table}.id", "{$table}.name")
            ->orderBy("{$table}.name")
            ->selectRaw("{$table}.name as label, COUNT(*) as total");

        return $this->legalCounts($query)->get()->map(fn ($row) => ['label' => $row->label, ...$this->legalRow($row)]);
    }

    /** Kelompok dengan izin "perlu perbaikan", beserta izin tersebut. */
    public function licensesNeedingFix(): EloquentCollection
    {
        return $this->organizations()
            ->whereHas('licenses', fn ($query) => $query->where('status', LicenseStatus::NeedsFix))
            ->with(['village:id,name', 'licenses' => fn ($query) => $query->where('status', LicenseStatus::NeedsFix)->orderBy('type')])
            ->orderBy('name')
            ->get(['id', 'name', 'village_id']);
    }

    /** Kelompok yang belum punya izin sama sekali (tidak ada izin terbit, diproses, atau perlu perbaikan). */
    public function withoutLicense(): EloquentCollection
    {
        return $this->organizations()
            ->whereDoesntHave('licenses', fn ($query) => $query->whereIn('status', self::LICENSE_HELD))
            ->with(['village:id,name', 'institutionType:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'village_id', 'institution_type_id']);
    }

    /**
     * Izin terbit yang habis berlaku dalam 60 hari ke depan. Izin tanpa tanggal habis tidak pernah masuk.
     */
    public function expiringLicenses(): EloquentCollection
    {
        $today = now()->startOfDay();

        return License::query()
            ->whereIn('organization_id', $this->organizations()->select('organizations.id'))
            ->where('status', LicenseStatus::Issued)
            ->whereNotNull('expires_on')
            ->whereBetween('expires_on', [$today->toDateString(), $today->copy()->addDays(self::EXPIRING_DAYS)->toDateString()])
            ->with('organization:id,name')
            ->orderBy('expires_on')
            ->get(['id', 'organization_id', 'type', 'expires_on']);
    }

    /** Laporan milik kelompok dalam cakupan (tanpa batas status/periode). */
    private function reports(): Builder
    {
        return MonthlyReport::query()->whereIn('monthly_reports.organization_id', $this->organizations()->select('organizations.id'));
    }

    private function verifiedReports(): Builder
    {
        return $this->reports()
            ->where('monthly_reports.status', ReportStatus::Verified)
            ->whereBetween('monthly_reports.period', $this->rangeDates());
    }

    private function productionQuery(): QueryBuilder
    {
        return ReportItem::query()
            ->join('monthly_reports', 'monthly_reports.id', '=', 'report_items.monthly_report_id')
            ->whereIn('monthly_reports.id', $this->verifiedReports()->select('monthly_reports.id'))
            ->whereNotNull('report_items.product_name')
            ->toBase()
            ->groupBy(DB::raw('LOWER(TRIM(report_items.product_name))'), DB::raw('LOWER(TRIM(report_items.production_unit))'));
    }

    /** @return array{0: string, 1: string} */
    private function rangeDates(): array
    {
        return [$this->from->format('Y-m-01'), $this->to->format('Y-m-01')];
    }

    /**
     * Jumlah kelompok wajib lapor per bulan (aktif/tidak aktif dan kewajiban lapor sudah dimulai), dengan
     * status kelompok saat ini. Satu query dikelompokkan per bulan mulai, lalu dijumlah kumulatif.
     *
     * @return array<string, int> Y-m => jumlah
     */
    private function obligatedPerMonth(): array
    {
        $starts = $this->organizations()
            ->whereIn('organizations.status', [OrganizationStatus::Active, OrganizationStatus::Inactive])
            ->toBase()
            ->groupBy('organizations.reporting_starts_on')
            ->selectRaw('organizations.reporting_starts_on as start, COUNT(*) as total')
            ->pluck('total', 'start');

        return $this->months()->mapWithKeys(fn (CarbonImmutable $month) => [
            $month->format('Y-m') => (int) $starts->filter(fn ($total, $start) => substr((string) $start, 0, 10) <= $month->format('Y-m-01'))->sum(),
        ])->all();
    }

    private function legalCounts(QueryBuilder $query): QueryBuilder
    {
        $exists = 'EXISTS (SELECT 1 FROM licenses WHERE licenses.organization_id = organizations.id AND licenses.status %s)';
        $held = implode(', ', array_fill(0, count(self::LICENSE_HELD), '?'));

        return $query->selectRaw(
            'SUM('.sprintf($exists, '= ?').') as issued, SUM('.sprintf($exists, '= ?').') as in_process, '
            .'SUM('.sprintf($exists, '= ?').') as needs_fix, SUM(NOT '.sprintf($exists, "IN ({$held})").') as without',
            [
                LicenseStatus::Issued->value, LicenseStatus::InProcess->value, LicenseStatus::NeedsFix->value,
                ...array_map(fn (LicenseStatus $status) => $status->value, self::LICENSE_HELD),
            ],
        );
    }

    private function legalRow(object $row): array
    {
        return [
            'total' => (int) $row->total,
            'issued' => (int) $row->issued,
            'in_process' => (int) $row->in_process,
            'needs_fix' => (int) $row->needs_fix,
            'without' => (int) $row->without,
        ];
    }
}
