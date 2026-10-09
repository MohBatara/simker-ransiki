<?php

namespace App\Enums;

enum Role: string
{
    use HasOptions;

    case Admin = 'admin';
    case Opd = 'opd';
    case Kelompok = 'kelompok';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator Distrik',
            self::Opd => 'OPD',
            self::Kelompok => 'Kelompok',
        };
    }
}
