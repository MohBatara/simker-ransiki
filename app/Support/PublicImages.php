<?php

namespace App\Support;

use App\Models\BusinessSector;
use App\Models\HeroSlide;
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

    public static function hero(HeroSlide $slide): array
    {
        return self::wide(fn (array $query) => route('public.hero-photo', ['slide' => $slide->id, ...$query]), $slide->photo_path, ImageVariants::HERO);
    }

    /** Foto kartu jenis kelembagaan (model atau objek dari PublicDirectory::institutionTypes()), atau null. */
    public static function institutionTypePhoto(object $type): ?array
    {
        return filled($type->photo_path ?? null)
            ? self::wide(fn (array $query) => route('public.type-photo', ['institutionType' => $type->id, ...$query]), $type->photo_path, ImageVariants::HERO)
            : null;
    }

    /** Foto kartu "Mengenal Distrik Ransiki" ke-$n. */
    public static function aboutCard(int $n, string $path): array
    {
        return self::wide(fn (array $query) => route('public.about-card-photo', ['n' => $n, ...$query]), $path, ImageVariants::HERO);
    }

    /** Foto blok "Potensi ekonomi" ke-$n (Profil Ransiki). */
    public static function potential(int $n, string $path): array
    {
        return self::wide(fn (array $query) => route('public.potential-photo', ['n' => $n, ...$query]), $path);
    }

    /** Foto bawaan di resources/aset, selama Admin belum mengaktifkan slide. */
    public static function heroDefault(): array
    {
        return self::wide(fn (array $query) => route('public.hero-default', $query), 'bawaan-1', ImageVariants::HERO);
    }

    private static function wide(callable $url, string $jpegPath, array $widths = ImageVariants::WIDE): array
    {
        $version = substr(md5($jpegPath), 0, 8);

        return [
            'webp' => collect($widths)->mapWithKeys(fn (int $width) => [$width => $url(['ukuran' => $width, 'format' => 'webp', 'v' => $version])])->all(),
            'fallback' => $url(['v' => $version]),
        ];
    }
}
