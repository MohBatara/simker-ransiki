<?php

namespace App\Enums;

/** Bagian foto hero yang dipertahankan saat foto dipotong (object-position). */
enum HeroFocus: string
{
    use HasOptions;

    case Top = 'top';
    case Center = 'center';
    case Bottom = 'bottom';

    public function label(): string
    {
        return match ($this) {
            self::Top => 'Atas',
            self::Center => 'Tengah',
            self::Bottom => 'Bawah',
        };
    }

    public function objectPosition(): string
    {
        return match ($this) {
            self::Top => 'object-top',
            self::Center => 'object-center',
            self::Bottom => 'object-bottom',
        };
    }
}
