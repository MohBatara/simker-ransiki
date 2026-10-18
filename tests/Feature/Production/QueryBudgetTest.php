<?php

use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;

/*
| Halaman daftar dan dasbor dengan data contoh (25 kelompok, 9 bulan laporan). Lazy loading sudah menjadi
| error di luar produksi (AppServiceProvider), jadi tes ini sekaligus menangkap N+1 lewat relasi. Batas
| jumlah query menangkap N+1 lewat query manual di dalam perulangan: angka saat ini paling banyak ~21,
| dan N+1 pada 25 kelompok akan melewatinya.
*/
const QUERY_BUDGET = 40;

function queriesFor(string $username, string $url): int
{
    test()->actingAs(User::where('username', $username)->firstOrFail());

    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });

    test()->get($url)->assertOk();

    return $count;
}

beforeEach(fn () => $this->seed(DemoSeeder::class));

test('halaman daftar dan dasbor tidak memicu N+1', function (string $username, string $url) {
    $url = strtr($url, [
        '{organization}' => Organization::orderBy('id')->value('id'),
        '{report}' => MonthlyReport::whereNotNull('submitted_at')->orderBy('id')->value('id'),
    ]);

    $count = queriesFor($username, $url);

    expect($count)->toBeLessThan(QUERY_BUDGET);
})->with([
    ['admin', '/admin'],
    ['admin', '/admin/kelompok'],
    ['admin', '/admin/kelompok/{organization}'],
    ['admin', '/admin/laporan'],
    ['admin', '/admin/laporan/{report}'],
    ['admin', '/admin/laporan/verifikasi'],
    ['admin', '/admin/kelompok/verifikasi-profil'],
    ['admin', '/admin/laporan/masalah'],
    ['admin', '/admin/dasbor/ekonomi'],
    ['admin', '/admin/dasbor/legalitas'],
    ['admin', '/admin/pengaturan/pengguna'],
    ['admin', '/admin/pengaturan/data-master/kampung'],
    ['admin', '/admin/pengaturan/data-master/opd'],
    ['admin', '/admin/website/berita'],
    ['admin', '/admin/kelompok/data-ganda'],
    ['admin', '/notifikasi'],
    ['opd.pertanian', '/opd'],
    ['opd.pertanian', '/opd/kelompok'],
    ['opd.pertanian', '/opd/laporan'],
    ['opd.pertanian', '/opd/laporan/verifikasi'],
    ['opd.pertanian', '/opd/laporan/masalah'],
    ['opd.pertanian', '/opd/dasbor/ekonomi'],
    ['kelompok1', '/kelompok'],
    ['kelompok1', '/kelompok/laporan'],
    ['kelompok1', '/kelompok/profil'],
    ['kelompok1', '/kelompok/produk'],
    ['kelompok1', '/kelompok/izin'],
]);

test('halaman publik tidak memicu N+1', function (string $url) {
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });

    $this->get($url)->assertOk();

    expect($count)->toBeLessThan(QUERY_BUDGET);
})->with(['/', '/direktori', '/produk', '/berita', '/profil', '/kontak', '/sitemap.xml']);
