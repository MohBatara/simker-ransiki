<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\Organization;
use App\Models\Product;

/**
 * Data tab Profil detail kelompok (Admin/OPD) dan halaman Profil Kelompok: laporan terakhir, foto
 * (foto produk lalu lampiran gambar), dan tautan pencarian Google Maps dari alamat.
 */
class OrganizationOverview
{
    public const PHOTOS = 4;

    public static function data(Organization $organization): array
    {
        $organization->loadMissing(['institutionType:id,name', 'village:id,name', 'sectors:id,name', 'opds:id,name,short_name', 'updater:id,name'])
            ->loadCount(['products', 'users']);

        $photos = $organization->products()->whereNotNull('photo_path')->latest('updated_at')->limit(self::PHOTOS)->get(['id', 'organization_id', 'name', 'photo_path'])
            ->map(fn (Product $product) => ['url' => $product->photoUrl(thumbnail: true), 'alt' => $product->name]);

        if ($photos->count() < self::PHOTOS) {
            $photos = $photos->concat($organization->attachments()->where('mime_type', 'like', 'image/%')->latest()->limit(self::PHOTOS - $photos->count())->get()
                ->map(fn (Attachment $attachment) => ['url' => route('attachments.show', $attachment), 'alt' => $attachment->original_name]));
        }

        return [
            'latestReport' => $organization->monthlyReports()->orderByDesc('period')->first(['id', 'organization_id', 'period', 'status', 'submitted_at']),
            'photos' => $photos->values(),
            'mapsUrl' => self::mapsUrl($organization),
        ];
    }

    /** Tautan pencarian (bukan titik koordinat): alamat + kampung + distrik + kabupaten. */
    public static function mapsUrl(Organization $organization): string
    {
        $query = collect([$organization->address, $organization->village?->name, 'Distrik Ransiki', 'Manokwari Selatan'])
            ->filter(fn ($part) => filled($part))
            ->join(', ');

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query);
    }
}
