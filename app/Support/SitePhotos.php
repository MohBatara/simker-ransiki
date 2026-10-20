<?php

namespace App\Support;

use App\Http\Controllers\Publik\SiteImageController;
use App\Models\SiteSetting;
use Illuminate\Support\Str;

/**
 * Penyimpanan foto Konten Website dari file sumber: dipakai unggahan Admin dan seeder foto resmi, sehingga
 * path, disk, varian, dan batas ukurannya selalu sama. Mengembalikan path JPEG yang disimpan di pengaturan/model.
 */
class SitePhotos
{
    /** Slide hero: hanya varian terkompres (WebP 640/1280/1920 + JPEG 1920), tanpa EXIF; file asli dibuang. */
    public static function storeHero(string $source): string
    {
        $path = 'site/hero-'.Str::uuid().'.jpg';
        ImageVariants::store(SiteImageController::DISK, $source, $path, ImageVariants::HERO, ImageVariants::HERO_SLIDE_BUDGET, jpegMaxSide: ImageVariants::HERO_MAX_SIDE);

        return $path;
    }

    /** Foto sampul, Tentang, banner Profil Ransiki, atau kantor (SiteSetting::PHOTO_SLOTS) + WebP 480/960/1600. */
    public static function storeSlot(string $slot, string $source): string
    {
        return self::storeWide('site/'.$slot, $source, in_array($slot, SiteSetting::BANNER_SLOTS, true) ? ImageVariants::HERO_BUDGET : []);
    }

    /** Foto blok "Potensi ekonomi" ke-$n di Profil Ransiki. */
    public static function storePotential(int $n, string $source): string
    {
        return self::storeWide("site/potensi-{$n}", $source);
    }

    /** Diproses seperti foto produk (putar sesuai EXIF, tanpa metadata) + varian WebP 480/960/1600. */
    public static function storeWide(string $prefix, string $source, array $budgets = []): string
    {
        $path = $prefix.'-'.Str::uuid().'.jpg';
        ImageVariants::store(SiteImageController::DISK, $source, $path, ImageVariants::WIDE, $budgets);

        return $path;
    }
}
