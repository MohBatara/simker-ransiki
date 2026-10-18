<?php

namespace App\Enums;

enum ReportStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Revision = 'revision';
    case Verified = 'verified';

    /** Label status laporan, SAMA di seluruh aplikasi (Admin, OPD, Kelompok, PDF, Excel). */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Menunggu verifikasi',
            self::Revision => 'Perlu revisi',
            self::Verified => 'Terverifikasi',
        };
    }

    /** Hanya draf dan laporan yang diminta revisi yang bisa diubah kelompok. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Revision], true);
    }
}
