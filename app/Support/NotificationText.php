<?php

namespace App\Support;

use App\Enums\FollowUpStatus;
use App\Enums\IssueKind;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * Kalimat notifikasi disusun saat ditampilkan dari data yang tersimpan (jenis, periode, nama
 * kelompok), sehingga teks bisa diperbaiki tanpa mengubah data lama. Notifikasi lama yang hanya
 * menyimpan "message" ditampilkan apa adanya.
 */
class NotificationText
{
    /** @return array{text: string, note: ?string} */
    public static function describe(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $period = isset($data['period']) ? Format::month($data['period'].'-01') : null;

        $text = match ($data['kind'] ?? null) {
            'report_submitted' => "Laporan {$period} {$data['organization_name']} "
                .(($data['resubmitted'] ?? false) ? 'diajukan ulang dan menunggu verifikasi.' : 'menunggu verifikasi.'),
            'report_reviewed' => match (ReviewAction::tryFrom($data['action'] ?? '')) {
                ReviewAction::Verified => "Laporan {$period} sudah diverifikasi.",
                ReviewAction::RevisionRequested => "Laporan {$period} perlu revisi.",
                ReviewAction::Reopened => "Laporan {$period} dibuka kembali untuk revisi oleh Admin distrik.",
                default => "Laporan {$period} sudah ditinjau.",
            },
            'report_reminder' => self::reminder($data, $period),
            'issue_follow_up' => (IssueKind::tryFrom($data['issue_kind'] ?? '')?->label() ?? 'Masalah')
                ." \"{$data['category']}\" dari laporan {$period}: ".mb_strtolower(FollowUpStatus::tryFrom($data['status'] ?? '')?->label() ?? 'diperbarui').'.',
            'report_overdue' => ($data['audience'] ?? null) === 'kelompok'
                ? "Laporan {$period} terlambat. Tenggatnya ".Format::date(Carbon::parse($data['deadline'])).'. Segera ajukan laporan.'
                : "{$data['organization_name']} belum mengajukan laporan {$period} sampai tenggat ".Format::date(Carbon::parse($data['deadline'])).'.',
            default => $data['message'] ?? 'Ada pemberitahuan baru.',
        };

        return ['text' => $text, 'note' => filled($data['note'] ?? null) ? $data['note'] : null];
    }

    /** Jumlah hari dihitung dari tanggal kirim, supaya teks tetap benar walau dibaca belakangan. */
    private static function reminder(array $data, string $period): string
    {
        $deadline = Carbon::parse($data['deadline']);

        if (($data['stage'] ?? null) === 'awal') {
            return "Laporan {$period} sudah bisa diajukan. Tenggat ".Format::date($deadline).'.';
        }

        $days = (int) Carbon::parse($data['sent_on'])->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false);

        return $days > 0
            ? "Tenggat laporan {$period} tinggal {$days} hari, yaitu ".Format::date($deadline).'.'
            : "Tenggat laporan {$period} adalah hari ini, ".Format::date($deadline).'.';
    }

    /** Data bersama untuk notifikasi tentang satu laporan. */
    public static function reportData(MonthlyReport $report): array
    {
        return [
            'monthly_report_id' => $report->id,
            'organization_id' => $report->organization_id,
            'organization_name' => $report->organization->name,
            'period' => $report->period->format('Y-m'),
        ];
    }
}
