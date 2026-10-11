<?php

namespace App\Support\Exports;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Organization;
use App\Support\Dashboards\Statistics;
use App\Support\Format;

/**
 * Rekap legalitas untuk "Unduh Excel" di Statistik → Legalitas. Isinya sama dengan layar: kelompok yang
 * terlihat pengguna (visibleTo) + filter aktif, dihitung per kelompok unik.
 */
class LegalRecap
{
    private const COLUMNS = ['Kelompok', 'Punya izin terbit', 'Izin sedang diproses', 'Izin perlu perbaikan', 'Belum punya izin'];

    public static function sheets(Statistics $stats, string $printedLine): array
    {
        $summary = $stats->legalSummary();
        $breakdown = fn (string $label, string $by) => [
            'name' => 'Per '.$label,
            'header' => [ucfirst($label), ...self::COLUMNS],
            'rows' => $stats->legalBreakdown($by)->map(fn (array $row) => [
                $row['label'], $row['total'], $row['issued'], $row['in_process'], $row['needs_fix'], $row['without'],
            ]),
        ];

        $organizations = $stats->organizations()
            ->with(['institutionType:id,name', 'village:id,name', 'licenses' => fn ($query) => $query->orderBy('type')])
            ->orderBy('name')
            ->get();
        $has = fn (Organization $organization, LicenseStatus $status) => $organization->licenses->contains('status', $status);

        return [
            [
                'name' => 'Ringkasan',
                'rows' => [
                    ['Rekap legalitas kelompok'],
                    [$printedLine],
                    [],
                    ['Jumlah kelompok (unik)', 'Jumlah'],
                    ['Kelompok', $summary['total']],
                    ['Punya minimal satu izin terbit', $summary['issued']],
                    ['Izin sedang diproses', $summary['in_process']],
                    ['Izin perlu perbaikan', $summary['needs_fix']],
                    ['Belum punya izin', $summary['without']],
                    [],
                    ['Satu kelompok bisa masuk lebih dari satu baris bila punya beberapa izin dengan status berbeda.'],
                    ['"Belum punya izin" = tidak ada izin terbit, sedang diproses, atau perlu perbaikan.'],
                ],
            ],
            $breakdown('jenis kelembagaan', 'type'),
            $breakdown('kampung', 'village'),
            [
                'name' => 'Kelompok',
                'header' => ['Nama kelompok', 'Jenis kelembagaan', 'Kampung', 'Izin terbit', 'Sedang diproses', 'Perlu perbaikan', 'Rincian izin', 'Perlu tindakan'],
                'rows' => $organizations->map(fn (Organization $organization) => [
                    $organization->name,
                    $organization->institutionType?->name,
                    $organization->village?->name,
                    $has($organization, LicenseStatus::Issued),
                    $has($organization, LicenseStatus::InProcess),
                    $has($organization, LicenseStatus::NeedsFix),
                    $organization->licenses->map(fn (License $license) => $license->type.' ('.$license->status->label()
                        .($license->expires_on ? ', berlaku sampai '.Format::date($license->expires_on) : '').')')->join('; '),
                    match (true) {
                        $has($organization, LicenseStatus::NeedsFix) => 'Perbaiki izin',
                        ! $has($organization, LicenseStatus::Issued) && ! $has($organization, LicenseStatus::InProcess) => 'Belum punya izin',
                        default => null,
                    },
                ]),
            ],
        ];
    }
}
