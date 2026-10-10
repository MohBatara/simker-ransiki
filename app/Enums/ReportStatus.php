<?php

namespace App\Enums;

enum ReportStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Revision = 'revision';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::Revision => 'Perlu revisi',
            self::Verified => 'Terverifikasi',
        };
    }

    /** Hanya draf dan laporan yang diminta revisi yang bisa diubah kelompok. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Revision], true);
    }

    /** Tone untuk komponen x-badge: ok, wait, fix, idle. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'idle',
            self::Submitted => 'wait',
            self::Revision => 'fix',
            self::Verified => 'ok',
        };
    }
}
