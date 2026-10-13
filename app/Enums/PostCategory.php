<?php

namespace App\Enums;

enum PostCategory: string
{
    use HasOptions;

    case News = 'berita';
    case Activity = 'kegiatan';

    public function label(): string
    {
        return match ($this) {
            self::News => 'Berita',
            self::Activity => 'Kegiatan',
        };
    }

    /** Ikon Heroicons untuk post tanpa foto sampul (Beranda). */
    public function icon(): string
    {
        return match ($this) {
            self::News => 'newspaper',
            self::Activity => 'calendar-days',
        };
    }
}
