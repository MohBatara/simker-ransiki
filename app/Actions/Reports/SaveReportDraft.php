<?php

namespace App\Actions\Reports;

use App\Enums\IssueKind;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Support\ReportForm;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Menyimpan isian form laporan. Baris produk dan masalah/kebutuhan disimpan ulang seluruhnya.
 */
class SaveReportDraft
{
    /**
     * @param  int|null  $version  updated_at (detik) yang dilihat pengguna; berbeda = ada yang menyimpan lebih dulu.
     */
    public function handle(MonthlyReport $report, User $user, array $data, ?int $version): MonthlyReport
    {
        return DB::transaction(function () use ($report, $user, $data, $version) {
            $report = MonthlyReport::lockForUpdate()->findOrFail($report->id);

            // Dicek ulang setelah baris dikunci: laporan bisa saja baru diajukan dari akun lain.
            Gate::forUser($user)->authorize('update', $report);

            if ($version !== null && $version !== $report->updated_at?->getTimestamp()) {
                abort(409, 'Laporan ini sudah diubah dari perangkat atau akun lain. Muat ulang halaman untuk melihat versi terbaru.');
            }

            $report->fill(Arr::only($data, ['no_activity', ...ReportForm::TEXT_FIELDS, ...ReportForm::INTEGER_FIELDS]));
            // Selalu diperbarui agar versi berubah walau yang diubah hanya baris produk/masalah.
            $report->updated_at = now();
            $report->save();

            $this->saveItems($report, $data['items'] ?? []);
            $this->saveIssues($report, IssueKind::Problem, $data['problems'] ?? []);
            $this->saveIssues($report, IssueKind::Support, $data['supports'] ?? []);

            return $report;
        });
    }

    private function saveItems(MonthlyReport $report, array $items): void
    {
        $productNames = $report->organization->products()->pluck('name', 'id');
        $report->items()->delete();

        foreach ($items as $item) {
            $item = Arr::only($item, ReportForm::ITEM_FIELDS);

            if (collect($item)->every(fn ($value) => blank($value))) {
                continue;
            }

            // Nama produk disimpan sebagai salinan; untuk produk terdaftar selalu diambil dari datanya.
            if (filled($item['product_id'] ?? null)) {
                $item['product_name'] = $productNames[$item['product_id']] ?? $item['product_name'] ?? null;
            }

            $report->items()->create($item);
        }
    }

    private function saveIssues(MonthlyReport $report, IssueKind $kind, array $rows): void
    {
        $report->issues()->where('kind', $kind)->delete();

        foreach ($rows as $row) {
            $row = Arr::only($row, ['category', 'description']);

            if (blank($row['category'] ?? null) && blank($row['description'] ?? null)) {
                continue;
            }

            $report->issues()->create([...$row, 'kind' => $kind]);
        }
    }
}
