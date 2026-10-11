<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Gambar banner header (x-page-header image): hanya varian yang sudah dikompres, tidak pernah foto asli.
 */
class BannerImage
{
    /** Dasbor: foto sampul dari Konten Website. */
    public static function cover(): ?array
    {
        return PublicImages::slot('sampul');
    }

    /**
     * Detail kelompok: foto produk kelompok yang mendatar → foto bidang usaha (Konten Website) → foto
     * sampul → null (pita forest polos).
     */
    public static function forOrganization(Organization $organization): ?array
    {
        $product = $organization->products()
            ->whereNotNull('photo_path')
            ->orderByDesc('is_featured')
            ->orderByDesc('updated_at')
            ->get(['id', 'organization_id', 'photo_path'])
            ->first(fn (Product $product) => self::isLandscape($product));

        if ($product) {
            return $product->photoSources();
        }

        $sector = $organization->sectors()->whereNotNull('business_sectors.photo_path')->orderBy('business_sectors.name')->first();

        return ($sector ? PublicImages::sectorPhoto($sector) : null) ?? self::cover();
    }

    /** Lebar > tinggi, dibaca dari versi kecil foto; hasilnya di-cache per berkas (hanya boolean). */
    public static function isLandscape(Product $product): bool
    {
        $path = $product->thumbnailPath() ?? $product->photo_path;

        return Cache::rememberForever('foto-mendatar:'.md5($path), function () use ($path) {
            $disk = Storage::disk(Product::PHOTO_DISK);
            $size = $disk->exists($path) ? @getimagesizefromstring($disk->get($path)) : false;

            return $size !== false && $size[0] > $size[1];
        });
    }
}
