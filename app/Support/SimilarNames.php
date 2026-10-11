<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Deteksi nama kelompok yang mirip (peringatan data ganda, bukan pemblokiran).
 *
 * Aturan (CLAUDE.md Bagian 4):
 * - Awalan umum dibuang dulu: kelompok, kelompok tani, kelompok nelayan, kub, bumdes, umkm, koperasi.
 * - "Tani" dan "nelayan" adalah penanda jenis; bila keduanya berbeda, nama dianggap tidak mirip.
 * - Bila angka di dalam nama berbeda, nama dianggap tidak mirip.
 * - Mirip bila sisa nama sama, kemiripan similar_text >= ambang (config simker.duplicates.min_similarity,
 *   bawaan 85%), atau jarak Levenshtein <= 2 untuk sisa nama >= 8 huruf.
 */
class SimilarNames
{
    private const PREFIXES = [
        'kelompok tani' => 'tani',
        'kelompok nelayan' => 'nelayan',
        'kelompok' => null,
        'koperasi' => null,
        'bumdes' => null,
        'umkm' => null,
        'kub' => null,
    ];

    private const ABBREVIATIONS = [
        'kel' => 'kelompok',
        'klp' => 'kelompok',
        'poktan' => 'kelompok tani',
    ];

    public const DEFAULT_MIN_SIMILARITY = 85;

    public const MAX_DISTANCE = 2;

    public const MIN_LETTERS_FOR_DISTANCE = 8;

    public static function areSimilar(string $first, string $second, ?int $minSimilarity = null): bool
    {
        return self::partsAreSimilar(self::split($first), self::split($second), $minSimilarity);
    }

    /**
     * Sama dengan areSimilar(), untuk hasil split() yang sudah dihitung (membandingkan banyak pasangan).
     *
     * @param  array{0: ?string, 1: string}  $first
     * @param  array{0: ?string, 1: string}  $second
     */
    public static function partsAreSimilar(array $first, array $second, ?int $minSimilarity = null): bool
    {
        [$firstMarker, $firstCore] = $first;
        [$secondMarker, $secondCore] = $second;

        if ($firstMarker && $secondMarker && $firstMarker !== $secondMarker) {
            return false;
        }

        if (self::numbers($firstCore) !== self::numbers($secondCore)) {
            return false;
        }

        if ($firstCore === $secondCore) {
            return true;
        }

        similar_text($firstCore, $secondCore, $percent);
        if ($percent >= ($minSimilarity ?? self::DEFAULT_MIN_SIMILARITY)) {
            return true;
        }

        $letters = min(strlen(preg_replace('/[^a-z]/', '', $firstCore)), strlen(preg_replace('/[^a-z]/', '', $secondCore)));

        return $letters >= self::MIN_LETTERS_FOR_DISTANCE
            && levenshtein($firstCore, $secondCore) <= self::MAX_DISTANCE;
    }

    /**
     * @return array{0: ?string, 1: string} [penanda jenis, sisa nama]
     */
    public static function split(string $name): array
    {
        $normalized = Str::of(Str::ascii($name))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->explode(' ')
            ->map(fn (string $word) => self::ABBREVIATIONS[$word] ?? $word)
            ->implode(' ');

        foreach (self::PREFIXES as $prefix => $marker) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix.' ')) {
                $core = trim(substr($normalized, strlen($prefix)));

                return [$marker, $core === '' ? $normalized : $core];
            }
        }

        return [null, $normalized];
    }

    /** Angka dibandingkan sebagai nilai, jadi "01" dan "1" dianggap sama. */
    private static function numbers(string $core): array
    {
        preg_match_all('/\d+/', $core, $matches);

        return array_map('intval', $matches[0]);
    }
}
