<?php

namespace App\Support;

use App\Models\Organization;

/**
 * Isi panel detail di samping daftar kelompok (desktop): foto, data inti, 4 produk, 3 laporan terakhir.
 */
class OrganizationPanel
{
    public const PRODUCTS = 4;

    public const REPORTS = 3;

    public static function data(Organization $organization): array
    {
        $organization->loadMissing(['institutionType:id,name', 'village:id,name', 'sectors:id,name', 'opds:id,name,short_name'])
            ->loadCount('products');

        return [
            'organization' => $organization,
            'image' => BannerImage::forOrganization($organization),
            'products' => $organization->products()
                ->orderByRaw('photo_path IS NULL')
                ->orderBy('name')
                ->limit(self::PRODUCTS)
                ->get(['id', 'organization_id', 'name', 'unit', 'photo_path']),
            'reports' => $organization->monthlyReports()
                ->orderByDesc('period')
                ->limit(self::REPORTS)
                ->get(['id', 'organization_id', 'period', 'status', 'submitted_at']),
        ];
    }
}
