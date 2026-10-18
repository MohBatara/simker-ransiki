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
}
