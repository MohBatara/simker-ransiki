<?php

namespace App\Support;

/**
 * Rentang kartu bento "Jenis kelembagaan" di beranda: kartu utama 2×2 lalu kartu kecil, grid selalu penuh untuk
 * 2 sampai 9 jenis. Tablet 2 kolom (kartu utama selebar baris), desktop 4 kolom (4 kartu kecil pertama di samping
 * kartu utama, sisanya per baris 4). Kartu kecil terakhir melebar mengisi sisa baris.
 */
class BentoGrid
{
    /** Kelas Tailwind ditulis utuh agar ikut terbaca saat build. */
    private const TABLET = ['1x1' => '', '2x1' => 'md:col-span-2'];

    private const DESKTOP = ['1x1' => 'lg:col-span-1', '2x1' => 'lg:col-span-2', '4x1' => 'lg:col-span-4', '2x2' => 'lg:col-span-2 lg:row-span-2'];

    /** Kelas kartu kecil ke-$index (mulai 0) dari $small kartu kecil. */
    public static function smallClasses(int $index, int $small): string
    {
        return trim(implode(' ', [
            self::TABLET[implode('x', self::span($index, $small, 2))],
            self::DESKTOP[implode('x', self::span($index, $small, 4))],
        ]));
    }

    /**
     * [kolom, baris] yang dipakai kartu kecil ke-$index pada grid $columns kolom (2 atau 4).
     *
     * @return array{0: int, 1: int}
     */
    public static function span(int $index, int $small, int $columns): array
    {
        $last = $index === $small - 1;

        if ($columns === 2) {
            return [$small % 2 === 1 && $last ? 2 : 1, 1];
        }

        // Empat sel di samping kartu utama (2 kolom × 2 baris).
        if ($small <= 4) {
            return match (true) {
                $small === 1 => [2, 2],
                $small === 2 => [2, 1],
                $small === 3 && $last => [2, 1],
                default => [1, 1],
            };
        }

        return match (($small - 4) % 4) {
            1 => $last ? [4, 1] : [1, 1],
            2 => $index >= $small - 2 ? [2, 1] : [1, 1],
            3 => $last ? [2, 1] : [1, 1],
            default => [1, 1],
        };
    }
}
