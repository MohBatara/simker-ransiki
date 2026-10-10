<?php

namespace App\Support;

use App\Enums\IssueKind;
use App\Models\MonthlyReport;
use App\Models\ReportIssue;
use App\Models\ReportItem;

/**
 * Susunan form laporan bulanan (bagian A–H) dan isian awalnya. Isian angka berupa teks dalam
 * format Indonesia karena dibaca ulang oleh NumberInput saat disimpan.
 */
class ReportForm
{
    public const SECTIONS = [
        'a' => 'Aktivitas',
        'b' => 'Produksi & penjualan',
        'c' => 'Sumber daya manusia',
        'd' => 'Keuangan',
        'e' => 'Permasalahan',
        'f' => 'Kebutuhan dukungan',
        'g' => 'Dokumentasi',
        'h' => 'Periksa & ajukan',
    ];

    public const MAX_ITEMS = 30;

    public const MAX_ISSUES = 10;

    public const INTEGER_FIELDS = ['activity_count', 'members_active', 'workforce_count', 'members_change', 'income_sales', 'income_other', 'costs'];

    public const TEXT_FIELDS = ['activity_summary', 'achievements'];

    public const ITEM_FIELDS = ['product_id', 'product_name', 'production_qty', 'production_unit', 'sales_qty', 'sales_unit', 'revenue'];

    /** Isian form dari laporan tersimpan. Urutan baris mengikuti id agar sama dengan kunci error saat diajukan. */
    public static function state(MonthlyReport $report): array
    {
        $report->loadMissing(['items', 'issues']);

        return [
            'no_activity' => $report->no_activity,
            'activity_summary' => $report->activity_summary ?? '',
            'achievements' => $report->achievements ?? '',
            ...collect(self::INTEGER_FIELDS)->mapWithKeys(fn (string $field) => [$field => NumberInput::display($report->{$field})])->all(),
            'items' => $report->items->sortBy('id')->map(fn (ReportItem $item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name ?? '',
                'production_qty' => NumberInput::display($item->production_qty, true),
                'production_unit' => $item->production_unit ?? '',
                'sales_qty' => NumberInput::display($item->sales_qty, true),
                'sales_unit' => $item->sales_unit ?? '',
                'revenue' => NumberInput::display($item->revenue),
            ])->values()->all(),
            'problems' => self::issueState($report, IssueKind::Problem),
            'supports' => self::issueState($report, IssueKind::Support),
        ];
    }

    /**
     * Data "Salin dari bulan lalu": laporan terdekat sebelum periode ini milik kelompok yang sama.
     * Hanya daftar produk + satuan dan angka SDM; jumlah produksi/penjualan dikosongkan.
     */
    public static function previous(MonthlyReport $report): ?array
    {
        $previous = MonthlyReport::query()
            ->where('organization_id', $report->organization_id)
            ->whereDate('period', '<', $report->period)
            ->orderByDesc('period')
            ->with('items')
            ->first();

        if (! $previous) {
            return null;
        }

        return [
            'period' => Format::month($previous->period),
            'members_active' => NumberInput::display($previous->members_active),
            'workforce_count' => NumberInput::display($previous->workforce_count),
            'items' => $previous->items->sortBy('id')
                ->filter(fn (ReportItem $item) => filled($item->product_name))
                ->map(fn (ReportItem $item) => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'production_unit' => $item->production_unit ?? '',
                    'sales_unit' => $item->sales_unit ?? '',
                ])->values()->all(),
        ];
    }

    /** Data tersimpan dalam bentuk yang sama dengan isian form, untuk validasi saat diajukan. */
    public static function storedData(MonthlyReport $report): array
    {
        $report->loadMissing(['items', 'issues']);

        return [
            ...$report->only(['no_activity', ...self::TEXT_FIELDS, ...self::INTEGER_FIELDS]),
            'items' => $report->items->sortBy('id')->map->only(self::ITEM_FIELDS)->values()->all(),
            'problems' => self::issueState($report, IssueKind::Problem),
            'supports' => self::issueState($report, IssueKind::Support),
        ];
    }

    private static function issueState(MonthlyReport $report, IssueKind $kind): array
    {
        return $report->issues->where('kind', $kind)->sortBy('id')
            ->map(fn (ReportIssue $issue) => [
                'category' => $issue->category ?? '',
                'description' => $issue->description ?? '',
            ])->values()->all();
    }
}
