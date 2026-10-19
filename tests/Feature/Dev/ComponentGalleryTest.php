<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;

// Meniru boot di APP_ENV=local, yang memuat routes/dev.php lewat bootstrap/app.php.
function enableDevRoutes(): void
{
    app()->detectEnvironment(fn () => 'local');
    Route::middleware('web')->group(base_path('routes/dev.php'));
    app('router')->getRoutes()->refreshNameLookups();
}

$pages = [
    'app' => ['/dev/komponen', 'Badge status'],
    'form' => ['/dev/komponen/form', 'Ajukan laporan'],
    'publik' => ['/dev/komponen/publik', 'Direktori kelompok'],
    'masuk' => ['/dev/komponen/masuk', 'Username atau email'],
];

test('route komponen tidak terdaftar di luar environment local', function (string $url) {
    expect(Route::has('dev.components.app'))->toBeFalse();

    $this->get($url)->assertNotFound();
})->with(array_map(fn ($page) => [$page[0]], $pages));

test('halaman komponen dapat dibuka di environment local', function (string $url, string $text) {
    enableDevRoutes();

    $this->get($url)->assertOk()->assertSee($text);
})->with($pages);

test('sidebar memakai label ringkas dengan nama menu lengkap untuk tooltip', function () {
    enableDevRoutes();

    $this->get('/dev/komponen')
        ->assertOk()
        ->assertSee('aria-label="Legalitas &amp; Izin"', false)
        ->assertSee('Izin')
        ->assertSee('Dasbor')
        ->assertDontSee('Dashboard');
});

test('bar aksi form menempel di bawah layar: Batal, Simpan, dan penanda perubahan belum disimpan', function () {
    view()->share('errors', new ViewErrorBag);
    $html = Blade::render('<x-admin.form-bar cancel="/kembali" submit="Simpan akun" />');

    expect($html)->toContain('sticky bottom-[var(--app-bottom,0px)]')
        ->toContain('x-data="formGuard(false)"')
        ->toContain('Perubahan belum disimpan')
        ->toContain('href="/kembali"')
        ->toContain('Simpan akun');
});

test('mode form menyembunyikan navigasi bawah dan menampilkan tombol kembali', function () {
    enableDevRoutes();

    $this->get('/dev/komponen/form')
        ->assertOk()
        ->assertSee('aria-label="Kembali"', false)
        ->assertDontSee('Menu lainnya')
        // Lonceng di header HP disembunyikan di mode form; lonceng desktop tetap ada.
        ->assertDontSee('data-bell="mobile"', false)
        ->assertSee('data-bell="desktop"', false);

    $this->get('/dev/komponen')
        ->assertOk()
        ->assertSee('Menu lainnya')
        ->assertSee('data-bell="mobile"', false);
});

test('baris tabel dengan href menjadi satu link dengan chevron, tanpa tombol buka', function () {
    $html = Blade::render(<<<'BLADE'
        <x-admin.table clickable>
            <x-admin.table.row href="/laporan/1">
                <x-admin.table.cell primary>Kelompok Contoh A</x-admin.table.cell>
                <x-admin.table.cell label="Pendapatan" numeric>Rp 1.000</x-admin.table.cell>
            </x-admin.table.row>
            <x-admin.table.row>
                <x-admin.table.cell primary>Kelompok Contoh B</x-admin.table.cell>
            </x-admin.table.row>
        </x-admin.table>
        BLADE);

    // Satu link di sel primary + "Lihat" di menu titik tiga.
    expect(substr_count($html, 'href="/laporan/1"'))->toBe(2)
        // Lapisan klik hanya sebesar sel primary (aman di Safari); sisa baris lewat data-row-href + JS.
        ->and(substr_count($html, 'data-row-href'))->toBe(1)
        ->and($html)->toContain('data-row-link')
        ->and(substr_count($html, 'chevron-right') + substr_count($html, 'm8.25 4.5 7.5 7.5-7.5 7.5'))->toBeGreaterThan(0)
        ->and($html)->not->toContain('>Buka<');
});

test('select tanpa nilai terpilih memakai warna placeholder', function () {
    view()->share('errors', new ViewErrorBag);
    $html = Blade::render('<x-select name="jenis" placeholder="Pilih jenis" :options="[\'a\' => \'A\']" />');

    expect($html)->toContain('has-[option:first-child:checked]:text-ink-muted');
});
