<?php

namespace App\Enums;

enum LicenseStatus: string
{
    use HasOptions;

    case None = 'none';
    case InProcess = 'in_process';
    case Issued = 'issued';
    case NeedsFix = 'needs_fix';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Belum ada',
            self::InProcess => 'Dalam proses',
            self::Issued => 'Terbit',
            self::NeedsFix => 'Perlu perbaikan',
        };
    }

    /** Tone untuk komponen x-badge: ok, wait, fix, idle. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::None => 'idle',
            self::InProcess => 'wait',
            self::Issued => 'ok',
            self::NeedsFix => 'fix',
        };
    }
}
