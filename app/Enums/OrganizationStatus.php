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
            self::Inactive => 'Tidak aktif sementara',
            self::Closed => 'Ditutup',
        };
    }

    /** Penjelasan akibat status, untuk modal ubah status. */
    public function description(): string
    {
        return match ($this) {
            self::Active => 'Wajib lapor dan bisa tampil di direktori publik.',
            self::Inactive => 'Tetap wajib lapor dan tetap bisa masuk. Bila dipublikasikan, tetap tampil di direktori dengan label.',
            self::Closed => 'Tidak bisa membuat laporan baru, tidak dihitung wajib lapor, dan tidak tampil di direktori publik. Data lama tetap tersimpan.',
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
