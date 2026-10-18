<?php

namespace App\Support\Dashboards;

use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use App\Models\ReportItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Angka seluruh distrik untuk dasbor Admin. Kelompok dihitung unik; angka ekonomi hanya dari laporan
 * terverifikasi dan NULL tidak dijumlahkan sebagai 0 (selalu disertai jumlah yang mengisi).
 */
class DistrictStats
{
    public const WAITING_DAYS = 7;

    /** @return array{total: int, statuses: Collection, types: Collection, sectors: Collection} */
    public function organizations(): array
    {
        $statuses = Organization::groupBy('status')->selectRaw('status, COUNT(*) as total')->pluck('total', 'status');

        $types = InstitutionType::query()
            ->withCount('organizations')
            ->orderByDesc('organizations_count')
            ->orderBy('name')
            ->get()
            ->map(fn (InstitutionType $type) => ['id' => $type->id, 'label' => $type->name, 'value' => $type->organizations_count]);

        $sectors = BusinessSector::query()
            ->withCount('organizations')
            ->orderByDesc('organizations_count')
            ->orderBy('name')
            ->get()
            ->map(fn (BusinessSector $sector) => ['id' => $sector->id, 'label' => $sector->name, 'value' => $sector->organizations_count]);

        return [
            'total' => (int) $statuses->sum(),
            'statuses' => collect(OrganizationStatus::cases())->mapWithKeys(fn (OrganizationStatus $status) => [$status->value => (int) ($statuses[$status->value] ?? 0)]),
            'types' => $types,
            'sectors' => $sectors,
        ];
    }

    /**
     * "Perlu perhatian" dasbor Admin. Angka laporan diturunkan dari MonthlyReport::verificationQueue (sama dengan tab
     * Perlu diverifikasi dan badge menu Laporan).
     *
     * @return array{waiting_total: int, without_opd: int, admin_queue: int, waiting: int, active_without_account: int}
     */
    public function attention(User $user): array
    {
        return [
            'waiting_total' => MonthlyReport::verificationQueue($user)->count(),
            'without_opd' => Organization::doesntHave('opds')->count(),
            'admin_queue' => MonthlyReport::verificationQueue($user)->needsAdminVerification()->count(),
            'waiting' => MonthlyReport::verificationQueue($user)->waitingLongerThan(self::WAITING_DAYS)->count(),
            'active_without_account' => Organization::where('status', OrganizationStatus::Active)->doesntHave('users')->count(),
        ];
    }

    /**
     * Ringkasan ekonomi resmi satu periode (hanya laporan terverifikasi). Untuk setiap angka: jumlah dan
     * berapa laporan yang mengisinya.
     */
    public function economy(CarbonImmutable $period): array
    {
        $date = $period->format('Y-m-d');
        $fields = ['income_sales', 'income_other', 'costs', 'profit'];

        $row = MonthlyReport::query()
            ->where('status', ReportStatus::Verified)
            ->whereDate('period', $date)
            ->selectRaw('COUNT(*) as reports')
            ->addSelect(collect($fields)->flatMap(fn (string $field) => [
                DB::raw("SUM({$field}) as {$field}_sum"),
                DB::raw("COUNT({$field}) as {$field}_filled"),
            ])->all())
            ->toBase()
            ->first();

        return [
            'reports' => (int) $row->reports,
            'obligated' => Organization::obligatedFor($period)->count(),
            'totals' => collect($fields)->mapWithKeys(fn (string $field) => [$field => [
                // NULL bila tidak ada yang mengisi, agar tidak tampil sebagai Rp 0.
                'sum' => $row->{$field.'_filled'} > 0 ? (int) $row->{$field.'_sum'} : null,
                'filled' => (int) $row->{$field.'_filled'},
            ]])->all(),
        ];
    }

    /**
     * Produksi per produk + satuan dari laporan terverifikasi. Nama dan satuan dibandingkan dalam huruf
     * kecil tanpa spasi tepi; satuan berbeda tidak pernah dijumlahkan.
     *
     * @return Collection<int, array{product: string, unit: ?string, quantity: ?string, filled: int, organizations: int}>
     */
    public function production(CarbonImmutable $period, int $limit = 10): Collection
    {
        return ReportItem::query()
            ->join('monthly_reports', 'monthly_reports.id', '=', 'report_items.monthly_report_id')
            ->where('monthly_reports.status', ReportStatus::Verified)
            ->whereDate('monthly_reports.period', $period->format('Y-m-d'))
            ->whereNotNull('report_items.product_name')
            ->groupBy(DB::raw('LOWER(TRIM(report_items.product_name))'), DB::raw('LOWER(TRIM(report_items.production_unit))'))
            ->select([
                DB::raw('MIN(TRIM(report_items.product_name)) as product'),
                DB::raw('LOWER(TRIM(report_items.production_unit)) as unit'),
                DB::raw('SUM(report_items.production_qty) as quantity'),
                DB::raw('COUNT(report_items.production_qty) as filled'),
                DB::raw('COUNT(DISTINCT monthly_reports.organization_id) as organizations'),
            ])
            ->orderByDesc('organizations')
            ->orderBy('product')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'product' => $row->product,
                'unit' => $row->unit,
                'quantity' => $row->filled > 0 ? $row->quantity : null,
                'filled' => (int) $row->filled,
                'organizations' => (int) $row->organizations,
            ]);
    }
}
