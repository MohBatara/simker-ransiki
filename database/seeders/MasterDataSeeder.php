<?php

namespace Database\Seeders;

use App\Models\BusinessSector;
use App\Models\InstitutionType;
use Illuminate\Database\Seeder;

/**
 * Daftar jenis kelembagaan dan bidang usaha (CLAUDE.md Bagian 4). Aman dijalankan ulang di produksi.
 */
class MasterDataSeeder extends Seeder
{
    public const INSTITUTION_TYPES = [
        'bumdes' => 'BUMDes',
        'kub' => 'KUB',
        'kdmp' => 'KDMP',
        'knmp' => 'KNMP',
        'kelompok_pertanian' => 'Kelompok Pertanian',
        'kelompok_perkebunan' => 'Kelompok Perkebunan',
        'kelompok_perikanan' => 'Kelompok Perikanan',
        'umkm' => 'UMKM',
        'lainnya' => 'Lainnya',
    ];

    public const BUSINESS_SECTORS = [
        'Pertanian', 'Perkebunan', 'Perikanan', 'UMKM', 'Perdagangan', 'Pengolahan Hasil', 'Lainnya',
    ];

    public function run(): void
    {
        foreach (self::INSTITUTION_TYPES as $code => $name) {
            InstitutionType::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (self::BUSINESS_SECTORS as $name) {
            BusinessSector::firstOrCreate(['name' => $name]);
        }
    }
}
