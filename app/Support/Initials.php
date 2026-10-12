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

    /** Kata jenis yang dibuang sebelum mengambil inisial kelompok. */
    private const TYPE_WORDS = ['kelompok', 'kel', 'kub', 'bumdes', 'kdmp', 'knmp', 'umkm', 'koperasi', 'tani', 'nelayan'];

    /**
     * Dua huruf untuk avatar kelompok: huruf awal dua kata pertama setelah kata jenis dibuang ("Kelompok Tani Kakao
     * Harapan" → "KH"), atau dua huruf pertama bila hanya tersisa satu kata ("KUB Rio" → "RI").
     */
    public static function organization(string $name): string
    {
        $all = collect(preg_split('/[^\p{L}\p{N}]+/u', $name, flags: PREG_SPLIT_NO_EMPTY));
        $words = $all->reject(fn (string $word) => in_array(mb_strtolower($word), self::TYPE_WORDS, true))->values();

        // Nama yang seluruhnya kata jenis (mis. "KDMP"): dua huruf pertama nama itu.
        if ($words->isEmpty()) {
            $words = $all->take(1)->values();
        }

        $initials = $words->count() === 1
            ? mb_substr($words[0], 0, 2)
            : mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1);

        return mb_strtoupper($initials);
    }
}
