<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

test('tombol kirim membawa teks proses dan label yang bisa diganti saat form dikirim', function () {
    $html = Blade::render('<x-button type="submit" processing="Menyimpan…">Simpan</x-button>');

    expect($html)->toContain('data-processing="Menyimpan…"')->toContain('<span data-button-label>Simpan</span>');
    expect(Blade::render('<x-button>Batal</x-button>'))->not->toContain('data-processing');
});

test('tombol kirim utama memakai teks proses yang sesuai', function () {
    $this->get('/login')->assertSee('data-processing="Memeriksa…"', false);

    $organization = Organization::factory()->create();
    $this->actingAs(User::factory()->kelompok($organization)->create())
        ->get('/kelompok/izin')->assertSee('data-processing="Menyimpan…"', false);
});

test('halaman 404 untuk pengguna login menawarkan kembali ke dasbornya', function () {
    $this->actingAs(User::factory()->opd(App\Models\Opd::factory()->create())->create())
        ->get('/opd/laporan/999999')
        ->assertNotFound()
        ->assertSee('Halaman tidak ditemukan')
        ->assertSee('Kembali')
        ->assertSee('href="'.route('opd.dashboard').'"', false)
        ->assertSee('Ke dasbor')
        ->assertDontSee('Kembali ke beranda');
});

test('halaman 404 untuk tamu menawarkan beranda', function () {
    $this->get('/direktori/tidak-ada')->assertNotFound()->assertSee('Ke beranda');
});

test('halaman 419 mengajak kembali dan masuk lagi, bukan memuat ulang kiriman', function () {
    Route::post('/uji-419', fn () => abort(419))->middleware('web');

    $this->post('/uji-419')->assertStatus(419)
        ->assertSee('Sesi berakhir')
        ->assertSee('Kembali ke halaman sebelumnya')
        ->assertSee('Masuk lagi');
});

test('halaman 500 dan 503 berbahasa Indonesia dengan tombol muat ulang', function (int $status, string $heading) {
    config(['app.debug' => false]);
    Route::get('/uji-error', fn () => $status === 500 ? throw new RuntimeException('rusak') : abort(503))->middleware('web');

    $this->get('/uji-error')->assertStatus($status)
        ->assertSee($heading)
        ->assertSee('Muat ulang')
        ->assertDontSee('Server Error')
        ->assertDontSee('rusak');
})->with([
    [500, 'Terjadi kesalahan di server'],
    [503, 'Sedang diperbarui'],
]);

test('halaman masuk dan error memakai komponen logo', function () {
    $this->get('/login')->assertSee('>SK</span>', false);
    $this->get('/direktori/tidak-ada')->assertSee('>SK</span>', false);
});
