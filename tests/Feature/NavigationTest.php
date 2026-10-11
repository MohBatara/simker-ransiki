<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\Navigation;

test('menu tiap peran sesuai CLAUDE.md bagian 6', function (Role $role, array $labels, array $primary) {
    $items = collect(Navigation::definitions($role));

    expect($items->pluck('label')->all())->toBe($labels)
        ->and($items->where('primary', true)->map(fn (array $item) => $item['short'] ?? $item['label'])->values()->all())->toBe($primary);
})->with([
    'kelompok' => [Role::Kelompok,
        ['Dasbor', 'Profil Kelompok', 'Produk', 'Legalitas & Izin', 'Laporan Bulanan', 'Notifikasi', 'Akun Saya'],
        ['Dasbor', 'Profil', 'Produk', 'Laporan']],
    'opd' => [Role::Opd,
        ['Dasbor', 'Kelompok Binaan', 'Verifikasi Laporan', 'Verifikasi Profil', 'Semua Laporan', 'Masalah & Kebutuhan', 'Statistik', 'Notifikasi', 'Akun Saya'],
        ['Dasbor', 'Kelompok', 'Verifikasi', 'Masalah']],
    'admin' => [Role::Admin,
        ['Dasbor Distrik', 'Semua Kelompok', 'Semua Laporan', 'Verifikasi Laporan', 'Verifikasi Profil', 'Masalah & Kebutuhan', 'Statistik', 'Pengguna', 'Data Master', 'Konten Website', 'Berita & Kegiatan', 'Notifikasi'],
        ['Dasbor', 'Kelompok', 'Laporan', 'Pengguna']],
]);

test('label ringkas sesuai tabel CLAUDE.md bagian 6', function () {
    $shorts = collect(Role::cases())
        ->flatMap(fn (Role $role) => Navigation::definitions($role))
        ->mapWithKeys(fn (array $item) => [$item['label'] => $item['short'] ?? $item['label']])
        ->all();

    expect($shorts)->toMatchArray([
        'Dasbor Distrik' => 'Dasbor', 'Profil Kelompok' => 'Profil', 'Legalitas & Izin' => 'Izin',
        'Laporan Bulanan' => 'Laporan', 'Semua Laporan' => 'Laporan', 'Akun Saya' => 'Akun',
        'Kelompok Binaan' => 'Kelompok', 'Semua Kelompok' => 'Kelompok', 'Verifikasi Laporan' => 'Verifikasi',
        'Data Master' => 'Master', 'Konten Website' => 'Konten', 'Masalah & Kebutuhan' => 'Masalah', 'Statistik' => 'Statistik',
        'Verifikasi Profil' => 'Profil', 'Berita & Kegiatan' => 'Berita',
    ]);
});

test('sidebar hanya menampilkan menu yang halamannya sudah ada', function () {
    $this->actingAs(User::factory()->create())
        ->get('/kelompok')
        ->assertOk()
        ->assertSee('aria-label="Dasbor"', false)
        ->assertSee('aria-label="Akun Saya"', false)
        ->assertSee('aria-label="Produk"', false)
        ->assertSee('aria-label="Laporan Bulanan"', false)
        ->assertSee('aria-label="Notifikasi"', false)
        ->assertSee('Keluar');
});
