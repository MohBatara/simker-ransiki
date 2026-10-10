<?php

namespace App\Support;

/**
 * Membaca angka yang diketik pengguna dalam format Indonesia. Logika yang sama ada di
 * resources/js/numbers.js untuk pratinjau "Terbaca: ..."; ubah keduanya bersamaan.
 *
 * Teks kosong menjadi NULL (tidak diketahui), "0" tetap 0. Teks yang tidak terbaca dikembalikan
 * apa adanya agar ditolak aturan validasi.
 */
class NumberInput
{
    /** Uang dan jumlah bulat: "Rp", spasi, titik, dan koma dibuang. */
    public static function integer(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $clean = preg_replace('/[\s.,]+/u', '', preg_replace('/^rp\.?/i', '', trim($value)));

        if ($clean === '') {
            return null;
        }

        return preg_match('/^-?\d{1,15}$/', $clean) ? (int) $clean : $value;
    }

    /**
     * Jumlah desimal. Koma = desimal. Titik = pemisah ribuan hanya bila setiap kelompok berisi tepat
     * 3 digit ("1.250", "1.250.000", "1.250,5"); selain itu titik = desimal ("12.5", "0.25").
     */
    public static function decimal(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $clean = preg_replace('/\s+/u', '', $value);

        if ($clean === '') {
            return null;
        }

        if (str_contains($clean, ',')) {
            return preg_match('/^(-?)([1-9]\d{0,2}(?:\.\d{3})+|\d+),(\d+)$/', $clean, $match)
                ? $match[1].str_replace('.', '', $match[2]).'.'.$match[3]
                : $value;
        }

        if (preg_match('/^-?[1-9]\d{0,2}(?:\.\d{3})+$/', $clean)) {
            return str_replace('.', '', $clean);
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $clean) ? $clean : $value;
    }

    /** Nilai tersimpan sebagai teks isian form: 1250000 → "1.250.000", 1250.50 → "1.250,5". */
    public static function display(int|float|string|null $value, bool $decimal = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! $decimal) {
            return number_format((int) $value, 0, ',', '.');
        }

        $formatted = number_format((float) $value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
