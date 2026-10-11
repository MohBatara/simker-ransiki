<?php

/**
 * Mengunci perhitungan kontras di CLAUDE.md Bagian 7 "Gaya website publik" (rumus WCAG 2.x).
 */
function luminance(array $rgb): float
{
    $channel = fn (float $c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

    return 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);
}

function hexRgb(string $hex): array
{
    return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
}

function contrast(array $a, array $b): float
{
    [$high, $low] = [max(luminance($a), luminance($b)), min(luminance($a), luminance($b))];

    return ($high + 0.05) / ($low + 0.05);
}

/** Warna hasil overlay forest dengan ketebalan $alpha di atas bagian foto yang putih (kondisi terburuk). */
function overlayOnWhite(float $alpha): array
{
    return array_map(fn (int $c) => $alpha * $c + (1 - $alpha) * 255, hexRgb('#0F2E22'));
}

test('pasangan warna publik memenuhi kontras teks', function (string $text, string $background, float $minimum) {
    expect(contrast(hexRgb($text), hexRgb($background)))->toBeGreaterThanOrEqual($minimum);
})->with([
    'putih di atas forest' => ['#FFFFFF', '#0F2E22', 4.5],
    'forest-soft di atas forest' => ['#B9C7BF', '#0F2E22', 4.5],
    'sun di atas forest' => ['#E9B949', '#0F2E22', 4.5],
    'ink di atas sun' => ['#1C1F1E', '#E9B949', 4.5],
]);

test('pasangan yang dilarang memang tidak lolos', function (string $text, string $background) {
    expect(contrast(hexRgb($text), hexRgb($background)))->toBeLessThan(4.5);
})->with([
    'putih di atas sun' => ['#FFFFFF', '#E9B949'],
    'sun di atas putih' => ['#E9B949', '#FFFFFF'],
]);

test('overlay hero menjaga teks putih terbaca di kondisi terburuk', function () {
    $white = hexRgb('#FFFFFF');

    // HP: padat 70%. Desktop: titik 50% (batas kanan area teks) 70%.
    expect(contrast($white, overlayOnWhite(0.70)))->toBeGreaterThanOrEqual(4.5)
        // Gradasi lurus 85% → 20% hanya 52,5% di titik 50% dan gagal; karena itu titik 70% wajib ada.
        ->and(contrast($white, overlayOnWhite(0.85 - 0.65 * 0.5)))->toBeLessThan(4.5);
});

test('overlay hero beranda menjaga aksen sun di judul terbaca di kondisi terburuk', function () {
    // HP padat 85%; desktop titik 55% (batas kanan area teks) 85%.
    expect(contrast(hexRgb('#E9B949'), overlayOnWhite(0.85)))->toBeGreaterThanOrEqual(4.5)
        ->and(contrast(hexRgb('#FFFFFF'), overlayOnWhite(0.85)))->toBeGreaterThanOrEqual(4.5)
        // Overlay 70% (overlay-forest) tidak cukup untuk teks sun.
        ->and(contrast(hexRgb('#E9B949'), overlayOnWhite(0.70)))->toBeLessThan(4.5);
});
