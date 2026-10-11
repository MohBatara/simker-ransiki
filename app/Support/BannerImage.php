<?php

namespace App\Support;

use App\Models\Organization;

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
     * Dasbor dan detail kelompok: hanya foto yang dikurasi Admin. Foto bidang usaha (Konten Website) →
     * foto sampul → null (pita forest polos). Foto produk/lampiran tidak dipakai sebagai latar.
     */
    public static function forOrganization(Organization $organization): ?array
    {
        $sector = $organization->sectors()->whereNotNull('business_sectors.photo_path')->orderBy('business_sectors.name')->first();

        return ($sector ? PublicImages::sectorPhoto($sector) : null) ?? self::cover();
    }
}
