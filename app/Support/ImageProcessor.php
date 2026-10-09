<?php

namespace App\Support;

use Intervention\Image\ImageManager;

/**
 * Memperkecil gambar dan membuang semua metadata (EXIF, termasuk lokasi GPS dari foto HP).
 * Gambar diputar sesuai orientasi EXIF lebih dulu, agar foto HP tidak tersimpan miring.
 */
class ImageProcessor
{
    public const MAX_SIDE = 1600;

    public const THUMBNAIL_SIDE = 400;

    public const JPEG_QUALITY = 80;

    /**
     * @param  'jpeg'|'png'|null  $format  null = pertahankan format asal (JPEG tetap JPEG, PNG tetap PNG)
     * @return array{contents: string, mime: string, extension: string}
     */
    public static function process(string $path, string $mime, int $maxSide = self::MAX_SIDE, ?string $format = null): array
    {
        // Gambar didekode penuh di memori; batas 8000 px per sisi sudah dicek saat validasi.
        @ini_set('memory_limit', '512M');

        $image = ImageManager::gd(autoOrientation: true, strip: true)->read($path);
        $image->scaleDown($maxSide, $maxSide);

        $format ??= $mime === 'image/png' ? 'png' : 'jpeg';

        return $format === 'png'
            ? ['contents' => (string) $image->toPng(), 'mime' => 'image/png', 'extension' => 'png']
            : ['contents' => (string) $image->toJpeg(quality: self::JPEG_QUALITY), 'mime' => 'image/jpeg', 'extension' => 'jpg'];
    }
}
