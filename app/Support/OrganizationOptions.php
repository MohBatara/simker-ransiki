<?php

namespace App\Support;

use App\Enums\LegalStatus;
use App\Enums\LicenseStatus;
use App\Enums\OrganizationStatus;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Opd;
use App\Models\Village;
use Illuminate\Support\Carbon;

/**
 * Pilihan untuk filter dan form kelompok, dipakai bersama oleh area Admin, OPD, dan Kelompok.
 */
class OrganizationOptions
{
    public static function all(): array
    {
        return [
            'types' => InstitutionType::orderBy('name')->pluck('name', 'id')->all(),
            'villages' => Village::orderBy('name')->pluck('name', 'id')->all(),
            'sectors' => BusinessSector::orderBy('name')->pluck('name', 'id')->all(),
            'opds' => Opd::orderBy('name')->pluck('name', 'id')->all(),
            'statuses' => OrganizationStatus::options(),
            'legalStatuses' => LegalStatus::options(),
            'licenseStatuses' => LicenseStatus::options(),
            'months' => collect(range(1, 12))->mapWithKeys(fn (int $month) => [$month => Carbon::create(2026, $month, 1)->translatedFormat('F')])->all(),
            'years' => collect(range(now()->year + 1, 2020))->mapWithKeys(fn (int $year) => [$year => (string) $year])->all(),
        ];
    }
}
