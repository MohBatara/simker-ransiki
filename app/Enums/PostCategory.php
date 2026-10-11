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
}
