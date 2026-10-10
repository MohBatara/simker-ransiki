<?php

namespace App\Support;

/**
 * Satuan umum untuk produk dan laporan. Di form dipilih dari daftar; isian bebas hanya lewat "Lainnya".
 * Satuan disimpan dalam huruf kecil tanpa spasi berlebih, sehingga data lama ("Kg ") ikut terbaca sama.
 */
class Units
{
    public const LIST = ['kg', 'ton', 'gram', 'liter', 'ekor', 'ikat', 'bungkus', 'karung', 'buah', 'lembar'];

    public const OTHER = 'lainnya';

    /** Selain teks dikembalikan apa adanya agar ditolak aturan validasi. */
    public static function normalize(mixed $unit): mixed
    {
        if ($unit === null || ! is_string($unit)) {
            return $unit;
        }

        $unit = mb_strtolower(preg_replace('/\s+/u', ' ', trim($unit)));

        return $unit === '' ? null : $unit;
    }

    /** @return array<string, string> untuk x-select */
    public static function options(): array
    {
        return array_combine(self::LIST, self::LIST) + [self::OTHER => 'Lainnya (tulis sendiri)'];
    }
}
