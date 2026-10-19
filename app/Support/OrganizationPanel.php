<?php

namespace App\Support;

use App\Enums\LicenseStatus;
use App\Enums\ReportStatus;
use App\Models\Organization;

/**
 * Isi popup pratinjau kelompok (daftar Kelompok, Verifikasi profil, data ganda): foto utama (OrganizationPhoto), data inti,
 * ringkasan angka, 4 produk, 3 laporan terakhir.
 */
class OrganizationPanel
{
    public const PRODUCTS = 4;

    public const REPORTS = 3;

    public static function data(Organization $organization): array
    {
        $organization->loadMissing(['institutionType:id,name', 'village:id,name', 'sectors:id,name', 'opds:id,name,short_name'])
            ->loadCount([
                'products',
                'licenses as issued_licenses_count' => fn ($query) => $query->where('status', LicenseStatus::Issued),
                'monthlyReports as verified_reports_count' => fn ($query) => $query->where('status', ReportStatus::Verified),
            ]);

        return [
            'organization' => $organization,
            'photo' => OrganizationPhoto::for($organization),
            'products' => $organization->products()
                ->orderByRaw('photo_path IS NULL')
                ->orderBy('name')
                ->limit(self::PRODUCTS)
                ->get(['id', 'organization_id', 'name', 'unit', 'photo_path']),
            'reports' => $organization->monthlyReports()
                ->orderByDesc('period')
                ->limit(self::REPORTS)
                ->with('enterer:id,role')
                ->get(['id', 'organization_id', 'period', 'status', 'submitted_at', 'entered_by']),
        ];
    }
}
