<?php

namespace App\Support;

use App\Models\BusinessSector;
use App\Models\Product;
use App\Models\SiteSetting;

/**
 * Sumber <picture> untuk komponen x-public.picture: ['webp' => [lebar => url], 'fallback' => url].
 */
class PublicImages
{
    public static function slot(string $slot): ?array
    {
        $path = SiteSetting::get(SiteSetting::PHOTO_SLOTS[$slot]);

        return $path ? self::wide(fn (array $query) => route('public.site-photo', ['slot' => $slot, ...$query]), $path) : null;
    }

    /**
     * Foto kartu bidang usaha dari App\Support\PublicDirectory::sectors(): foto bidang, foto produk, atau null.
     */
    public static function sector(object $sector): ?array
    {
        return match ($sector->photo['kind'] ?? null) {
            'sector' => self::wide(fn (array $query) => route('public.sector-photo', ['sector' => $sector->id, ...$query]), $sector->photo['path']),
            'product' => (new Product(['photo_path' => $sector->photo['path']]))->forceFill(['id' => $sector->photo['id']])->photoSources(),
            default => null,
        };
    }

    /** Foto bidang usaha dari Konten Website (route publik), atau null bila belum ada. */
    public static function sectorPhoto(BusinessSector $sector): ?array
    {
        return $sector->photo_path ? self::wide(fn (array $query) => route('public.sector-photo', ['sector' => $sector->id, ...$query]), $sector->photo_path) : null;
    }

    private static function wide(callable $url, string $jpegPath): array
    {
        $version = substr(md5($jpegPath), 0, 8);

        return [
            'webp' => collect(ImageVariants::WIDE)->mapWithKeys(fn (int $width) => [$width => $url(['ukuran' => $width, 'format' => 'webp', 'v' => $version])])->all(),
            'fallback' => $url(['v' => $version]),
        ];
    }
}
