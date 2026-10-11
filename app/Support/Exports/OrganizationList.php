<?php

namespace App\Support\Exports;

use App\Models\License;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar kelompok untuk "Unduh Excel" (Admin dan OPD). Query yang diterima sudah dibatasi
 * Organization::visibleTo dan filter daftar, sehingga isinya sama dengan tabel di layar.
 */
class OrganizationList
{
    public const HEADER = [
        'Nama kelompok', 'Jenis kelembagaan', 'Kampung', 'Bidang usaha', 'OPD terkait', 'Status',
        'Ketua', 'Telepon', 'Email', 'Alamat', 'Jumlah anggota', 'Status hukum', 'Izin', 'Tampil di direktori publik',
    ];

    public static function sheet(Builder $query): array
    {
        $organizations = $query
            ->with(['institutionType:id,name', 'village:id,name', 'sectors:id,name', 'opds:id,name,short_name', 'licenses:id,organization_id,type,status'])
            ->orderBy('name')
            ->get();

        return [
            'name' => 'Kelompok',
            'header' => self::HEADER,
            'rows' => $organizations->map(fn (Organization $organization) => [
                $organization->name,
                $organization->institutionType?->name,
                $organization->village?->name,
                $organization->sectors->pluck('name')->sort()->join('; '),
                $organization->opds->map(fn ($opd) => $opd->short_name ?: $opd->name)->sort()->join('; '),
                $organization->status->label(),
                $organization->leader_name,
                $organization->phone,
                $organization->email,
                $organization->address,
                $organization->members_count,
                $organization->legal_status->label(),
                $organization->licenses->sortBy('type')->map(fn (License $license) => $license->type.' ('.$license->status->label().')')->join('; '),
                $organization->is_public,
            ]),
        ];
    }
}
