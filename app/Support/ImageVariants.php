<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Varian gambar untuk website publik: satu JPEG utama (cadangan) dan beberapa WebP per lebar untuk
 * srcset. Foto dibaca sekali lalu diperkecil per varian. Tanpa dukungan WebP, hanya JPEG yang dibuat.
 */
class ImageVariants
{
    /** Foto lebar: sampul, "Tentang", dan foto bidang usaha. */
    public const WIDE = [480, 960, 1600];

    /** Foto produk di kartu. */
    public const PRODUCT = [400, 800];

    /** Hero beranda 1600 px dibatasi sekitar 200 KB. */
    public const HERO_BUDGET = [1600 => 200 * 1024];

    public static function webpSupported(): bool
    {
        return function_exists('imagewebp') && (bool) (imagetypes() & IMG_WEBP);
    }

    /**
     * @param  array<int, int>  $budgets  lebar => batas byte; kualitas WebP diturunkan 70 → 50 sampai muat
     */
    public static function store(string $disk, string $sourcePath, string $jpegPath, array $webpWidths, array $budgets = [], bool $writeJpeg = true): void
    {
        // Gambar didekode penuh di memori; batas 8000 px per sisi sudah dicek saat validasi.
        @ini_set('memory_limit', '512M');

        $image = ImageManager::gd(autoOrientation: true, strip: true)->read($sourcePath);
        $storage = Storage::disk($disk);

        if ($writeJpeg) {
            $storage->put($jpegPath, (string) (clone $image)->scaleDown(ImageProcessor::MAX_SIDE, ImageProcessor::MAX_SIDE)->toJpeg(quality: ImageProcessor::JPEG_QUALITY));
        }

        if (! self::webpSupported()) {
            return;
        }

        foreach ($webpWidths as $width) {
            $scaled = (clone $image)->scaleDown(width: $width);
            $quality = 70;

            do {
                $encoded = (string) $scaled->toWebp(quality: $quality);
                $quality -= 5;
            } while (isset($budgets[$width]) && strlen($encoded) > $budgets[$width] && $quality >= 50);

            $storage->put(self::path($jpegPath, $width), $encoded);
        }
    }

    /** "site/sampul-x.jpg" + 960 → "site/sampul-x-960.webp". */
    public static function path(string $jpegPath, int $width): string
    {
        return preg_replace('/\.jpg$/', "-{$width}.webp", $jpegPath);
    }

    /** @param  list<int>  $widths */
    public static function delete(string $disk, ?string $jpegPath, array $widths): void
    {
        if (! $jpegPath) {
            return;
        }

        Storage::disk($disk)->delete([$jpegPath, ...array_map(fn (int $width) => self::path($jpegPath, $width), $widths)]);
    }

    /**
     * Path file untuk permintaan ?ukuran=&format=webp. Bila varian WebP belum ada (foto lama, atau server
     * tanpa WebP), kembali ke JPEG agar gambar tidak pernah rusak.
     */
    public static function resolve(string $disk, string $jpegPath, ?string $size, ?string $format, array $widths): array
    {
        if ($format === 'webp' && in_array((int) $size, $widths, true)) {
            $variant = self::path($jpegPath, (int) $size);

            if (Storage::disk($disk)->exists($variant)) {
                return [$variant, 'image/webp'];
            }
        }

        return [$jpegPath, 'image/jpeg'];
    }
}
