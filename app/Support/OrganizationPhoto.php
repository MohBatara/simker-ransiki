<?php

namespace App\Support;

use App\Models\Organization;

/**
 * "Foto utama" kelompok (SATU tempat; belum ada kolom foto profil kelompok): foto produk terbaru → lampiran gambar
 * kelompok terbaru → foto bidang usaha (Konten Website) → null (inisial). Foto produk dan lampiran disimpan di disk privat
 * dan dibuka lewat route yang memeriksa Policy (product-photos.show, attachments.show); foto bidang usaha lewat route
 * publik. Foto sampul situs tidak dipakai karena bukan foto kelompok.
 *
 * @return array{url: string, alt: string, source: string}|null
 */
class OrganizationPhoto
{
    public static function for(Organization $organization): ?array
    {
        $product = $organization->products()->whereNotNull('photo_path')->latest('updated_at')->first(['id', 'organization_id', 'name', 'photo_path']);
        if ($product) {
            return ['url' => $product->photoUrl(), 'alt' => $product->name, 'source' => 'product'];
        }

        $attachment = $organization->attachments()->where('mime_type', 'like', 'image/%')->latest()->first();
        if ($attachment) {
            return ['url' => route('attachments.show', $attachment), 'alt' => 'Foto '.$organization->name, 'source' => 'attachment'];
        }

        $sector = $organization->sectors()->whereNotNull('business_sectors.photo_path')->orderBy('business_sectors.name')->first();
        $sectorPhoto = $sector ? PublicImages::sectorPhoto($sector) : null;

        return $sectorPhoto ? ['url' => $sectorPhoto['webp'][960] ?? $sectorPhoto['fallback'], 'alt' => 'Bidang usaha '.$sector->name, 'source' => 'sector'] : null;
    }
}
