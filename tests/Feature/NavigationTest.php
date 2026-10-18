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
        ['Dasbor', 'Kelompok binaan', 'Laporan', 'Notifikasi', 'Akun Saya'],
        ['Dasbor', 'Kelompok', 'Laporan']],
    'admin' => [Role::Admin,
        ['Dasbor', 'Kelompok', 'Laporan', 'Website', 'Pengaturan', 'Notifikasi', 'Akun Saya'],
        ['Dasbor', 'Kelompok', 'Laporan', 'Website']],
]);

test('label ringkas sesuai tabel CLAUDE.md bagian 6', function () {
    $shorts = collect(Role::cases())
        ->flatMap(fn (Role $role) => Navigation::definitions($role))
        ->mapWithKeys(fn (array $item) => [$item['label'] => $item['short'] ?? $item['label']])
        ->all();

    expect($shorts)->toMatchArray([
        'Dasbor' => 'Dasbor', 'Profil Kelompok' => 'Profil', 'Legalitas & Izin' => 'Izin',
        'Laporan Bulanan' => 'Laporan', 'Laporan' => 'Laporan', 'Akun Saya' => 'Akun',
        'Kelompok binaan' => 'Kelompok', 'Kelompok' => 'Kelompok', 'Website' => 'Website', 'Pengaturan' => 'Pengaturan',
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
