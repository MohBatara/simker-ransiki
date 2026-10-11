<?php

namespace Database\Seeders;

use App\Models\Opd;
use App\Models\Village;
use Illuminate\Database\Seeder;

/**
 * Data awal server produksi: hanya data master, tanpa data contoh dan tanpa akun.
 * Aman dijalankan ulang (php artisan db:seed --class=ProductionSeeder --force).
 * Admin pertama dibuat dengan php artisan simker:create-admin.
 */
class ProductionSeeder extends Seeder
{
    /**
     * short_name => nama resmi OPD. Masih kosong sampai daftar resmi dari klien diterima;
     * sementara itu OPD ditambahkan Admin lewat Data Master.
     *
     * @var array<string, string>
     */
    public const OPDS = [];

    /**
     * Nama resmi kampung di Distrik Ransiki. Masih kosong sampai daftar resmi diterima.
     *
     * @var list<string>
     */
    public const VILLAGES = [];

    public function run(): void
    {
        $this->call(MasterDataSeeder::class);

        foreach (static::OPDS as $shortName => $name) {
            Opd::firstOrCreate(['short_name' => $shortName], ['name' => $name]);
        }

        foreach (static::VILLAGES as $name) {
            Village::firstOrCreate(['name' => $name]);
        }
    }
}
