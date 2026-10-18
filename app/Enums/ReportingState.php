<?php

namespace App\Enums;

use App\Models\MonthlyReport;
use Carbon\CarbonInterface;

/**
 * Status pelaporan satu kelompok untuk satu periode (CLAUDE.md Bagian 5). Satu-satunya tempat aturan
 * "terlambat" untuk satu kelompok; versi query-nya Organization::scopeOverdueFor, dipakai dasbor dan
 * notifikasi tenggat.
 *
 * Terlambat = lewat tenggat dan belum pernah diajukan (belum dibuat atau masih draf). Laporan yang
 * sudah diajukan tidak pernah terlambat, termasuk yang menunggu verifikasi atau diminta revisi.
 */
enum ReportingState: string
{
    case Verified = 'verified';
    case Submitted = 'submitted';
    case Revision = 'revision';
    case Draft = 'draft';
    case Missing = 'missing';
    case Overdue = 'overdue';

    public static function for(?MonthlyReport $report, CarbonInterface $period): self
    {
        if ($report?->submitted_at !== null) {
            return match ($report->status) {
                ReportStatus::Verified => self::Verified,
                ReportStatus::Revision => self::Revision,
                default => self::Submitted,
            };
        }

        if (MonthlyReport::isPastDeadline($period)) {
            return self::Overdue;
        }

        return $report ? self::Draft : self::Missing;
    }

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'Terverifikasi',
            self::Submitted => 'Menunggu verifikasi',
            self::Revision => 'Perlu revisi',
            self::Draft => 'Draf',
            self::Missing => 'Belum dibuat',
            self::Overdue => 'Terlambat',
        };
    }
}
