<?php

namespace App\Enums;

enum LegalStatus: string
{
    use HasOptions;

    case Unknown = 'unknown';
    case None = 'none';
    case InProcess = 'in_process';
    case Registered = 'registered';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Belum diketahui',
            self::None => 'Belum terdaftar',
            self::InProcess => 'Dalam proses',
            self::Registered => 'Terdaftar',
        };
    }

    /** Tone untuk komponen x-badge: ok, wait, fix, idle. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Unknown => 'idle',
            self::None => 'idle',
            self::InProcess => 'wait',
            self::Registered => 'ok',
        };
    }
}
