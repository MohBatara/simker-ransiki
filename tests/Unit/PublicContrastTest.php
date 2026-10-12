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

/** Warna hasil teks putih dengan opasitas $alpha di atas latar $background. */
function whiteAt(float $alpha, string $background): array
{
    return array_map(fn (int $c) => $alpha * 255 + (1 - $alpha) * $c, hexRgb($background));
}

test('palet tanah (beranda): pasangan teks dan latar memenuhi 4,5:1', function (array $text, string $background) {
    expect(contrast($text, hexRgb($background)))->toBeGreaterThanOrEqual(4.5);
})->with([
    'putih di atas clay' => [[255, 255, 255], '#6B4430'],
    'putih di atas sage' => [[255, 255, 255], '#4E6F4A'],
    'putih 85% di atas forest' => [whiteAt(0.85, '#0F2E22'), '#0F2E22'],
    'putih 85% di atas clay' => [whiteAt(0.85, '#6B4430'), '#6B4430'],
    'putih 85% di atas sage' => [whiteAt(0.85, '#4E6F4A'), '#4E6F4A'],
    'putih 80% di atas forest (Cara kerja)' => [whiteAt(0.80, '#0F2E22'), '#0F2E22'],
    'ink di atas sand' => [hexRgb('#1C1F1E'), '#D8BF98'],
    'ink-soft di atas sand' => [hexRgb('#4A504D'), '#D8BF98'],
    'ink di atas cream' => [hexRgb('#1C1F1E'), '#F3EBDD'],
    'ink-soft di atas cream' => [hexRgb('#4A504D'), '#F3EBDD'],
    'brand (ikon) di lingkaran putih' => [hexRgb('#1F5A47'), '#FFFFFF'],
]);

test('palet tanah: pasangan yang dilarang memang tidak lolos', function (array $text, string $background) {
    expect(contrast($text, hexRgb($background)))->toBeLessThan(4.5);
})->with([
    'putih di atas sand' => [[255, 255, 255], '#D8BF98'],
    'putih di atas cream' => [[255, 255, 255], '#F3EBDD'],
    // Alasan kartu sage memakai putih 85%, bukan 80%.
    'putih 80% di atas sage' => [whiteAt(0.80, '#4E6F4A'), '#4E6F4A'],
]);

/** Warna latar putih $alpha di atas $background (lingkaran "+N" bg-paper/15). */
function tintOn(float $alpha, string $background): array
{
    return whiteAt($alpha, $background);
}

test('kartu bento jenis kelembagaan: pasangan teks memenuhi 4,5:1', function (array $text, array $background) {
    expect(contrast($text, $background))->toBeGreaterThanOrEqual(4.5);
})->with([
    'inisial avatar brand di brand-tint' => [hexRgb('#1F5A47'), hexRgb('#E7F0EC')],
    '+N putih di lingkaran putih 15% (forest)' => [[255, 255, 255], tintOn(0.15, '#0F2E22')],
    '+N putih di lingkaran putih 15% (clay)' => [[255, 255, 255], tintOn(0.15, '#6B4430')],
    '+N ink di lingkaran putih (cream)' => [hexRgb('#1C1F1E'), [255, 255, 255]],
    'teks putih kartu utama di atas foto + lapisan forest 70%' => [[255, 255, 255], overlayOnWhite(0.70)],
]);

/** Warna kartu yang tertutup foto kartu kecil sebanyak $alpha di tepi kanan teks (foto putih atau hitam, terburuk). */
function photoOn(string $card, float $alpha, int $photo): array
{
    return array_map(fn (int $c) => $alpha * $photo + (1 - $alpha) * $c, hexRgb($card));
}

test('kartu kecil berfoto: teks tetap 4,5:1 di tepi kanannya (foto menutupi paling banyak 10%)', function (array $text, array $background) {
    // Foto 45% kanan, mask 0 → penuh di 60% lebarnya, opasitas 85%; teks berakhir di 58% isi kartu: foto ≤ 8,3%.
    expect(contrast($text, $background))->toBeGreaterThanOrEqual(4.5);
})->with([
    'putih 85% di clay + 10% foto putih' => [whiteAt(0.85, '#6B4430'), photoOn('#6B4430', 0.10, 255)],
    'putih 85% di forest + 10% foto putih' => [whiteAt(0.85, '#0F2E22'), photoOn('#0F2E22', 0.10, 255)],
    'ink-soft di cream + 10% foto hitam' => [hexRgb('#4A504D'), photoOn('#F3EBDD', 0.10, 0)],
    'ink di cream + 10% foto hitam' => [hexRgb('#1C1F1E'), photoOn('#F3EBDD', 0.10, 0)],
]);
