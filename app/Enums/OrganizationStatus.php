<?php

namespace App\Enums;

enum OrganizationStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak aktif',
            self::Closed => 'Ditutup',
        };
    }

    /** Tone untuk komponen x-badge: ok, wait, fix, idle. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'ok',
            self::Inactive => 'idle',
            self::Closed => 'idle',
        };
    }
}
