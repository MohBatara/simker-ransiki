<?php

namespace App\Actions\Reports;

use App\Enums\IssueKind;
use App\Enums\ReviewAction;
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

            // OPD/Admin yang mengisi atas nama kelompok tercatat di riwayat (sekali per akun), dan karena itu
            // tidak boleh memverifikasi laporan ini (pemisahan tugas).
            if (! $user->isKelompok() && ! $report->reviews()->where('user_id', $user->id)->where('action', ReviewAction::Entered)->exists()) {
                $report->reviews()->create(['user_id' => $user->id, 'action' => ReviewAction::Entered]);
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

    /**
     * Baris yang membawa id miliknya diperbarui di tempat, agar riwayat tindak lanjutnya tetap tersambung
     * (mis. saat laporan direvisi). Baris tanpa id dibuat baru; baris yang tidak dikirim lagi dihapus.
     */
    private function saveIssues(MonthlyReport $report, IssueKind $kind, array $rows): void
    {
        $existing = $report->issues()->where('kind', $kind)->get()->keyBy('id');
        $kept = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $row = Arr::only($row, ['category', 'description']);

            if (blank($row['category'] ?? null) && blank($row['description'] ?? null)) {
                continue;
            }

            if ($issue = $existing->get($id)) {
                $issue->update($row);
                $kept[] = $id;
            } else {
                $report->issues()->create([...$row, 'kind' => $kind]);
            }
        }

        $existing->except($kept)->each->delete();
    }
}
