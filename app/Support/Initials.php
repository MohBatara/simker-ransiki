<?php

namespace App\Support;

/** Dua huruf awal untuk avatar dan petak foto kosong: "KUB Contoh 01" → "KC". */
class Initials
{
    public static function of(?string $name): string
    {
        return collect(preg_split('/\s+/', trim((string) $name)))
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }
}
