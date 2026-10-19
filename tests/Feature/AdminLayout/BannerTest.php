<?php

use App\Models\BusinessSector;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\BannerImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Cache::flush();
    $this->organization = Organization::factory()->create();
});

test('banner kelompok: foto bidang usaha, lalu foto sampul, lalu tanpa gambar', function () {
    $sector = BusinessSector::factory()->create(['photo_path' => 'site/bidang.jpg']);

    expect(BannerImage::forOrganization($this->organization))->toBeNull();

    SiteSetting::put(['cover_photo_path' => 'site/sampul.jpg']);
    expect(BannerImage::forOrganization($this->organization)['fallback'])->toContain('/foto-sampul');

    $this->organization->sectors()->attach($sector);
    expect(BannerImage::forOrganization($this->organization)['fallback'])->toContain('/foto-bidang/'.$sector->id);
});

test('foto produk tidak pernah dipakai sebagai latar banner', function () {
    $product = Product::factory()->for($this->organization)->create(['photo_path' => 'products/mendatar.jpg']);
    Storage::disk(Product::PHOTO_DISK)->put($product->photo_path, imageBytes(800, 400));

    expect(BannerImage::forOrganization($this->organization))->toBeNull();

    SiteSetting::put(['cover_photo_path' => 'site/sampul.jpg']);
    expect(BannerImage::forOrganization($this->organization)['fallback'])->toContain('/foto-sampul')
        ->not->toContain('/foto-produk/');
});

test('Admin/OPD: header halaman terang (x-admin.page-header), tanpa pita forest, juga di halaman form', function () {
    $this->actingAs(User::factory()->admin()->create());

    foreach (['/admin/kelompok', '/admin/kelompok/tambah', '/admin/laporan'] as $url) {
        $this->get($url)->assertOk()->assertDontSee('app-band md:', false)->assertSee('text-admin-title font-semibold', false);
    }
});

test('dasbor Admin/OPD: banner sapaan bergradasi lembut brand, tanpa forest dan tanpa banner foto walau ada foto sampul', function () {
    SiteSetting::put(['cover_photo_path' => 'site/sampul.jpg']);
    Carbon\CarbonImmutable::setTestNow(Carbon\CarbonImmutable::parse('2026-10-18 09:00', 'Asia/Jayapura'));

    $this->actingAs(User::factory()->opd(Opd::factory()->create())->create(['name' => 'Yosias Kafiar']))->get('/opd')
        ->assertOk()
        ->assertSee('Selamat pagi, Yosias')
        ->assertSee('bg-linear-to-r from-brand-50 to-brand-mist', false)
        ->assertDontSee('bg-forest', false)
        ->assertDontSee('app-band-photo max-md:relative', false)
        ->assertDontSee('overlay-forest', false);

    Carbon\CarbonImmutable::setTestNow();
});

test('peran Kelompok memakai kepala terang yang sama dengan Admin/OPD, tanpa banner foto', function () {
    SiteSetting::put(['cover_photo_path' => 'site/sampul.jpg']);

    foreach (['/kelompok', '/kelompok/profil', '/kelompok/produk', '/kelompok/laporan'] as $url) {
        $this->actingAs(User::factory()->kelompok($this->organization)->create())->get($url)
            ->assertOk()
            ->assertDontSee('overlay-forest', false)
            ->assertDontSee('app-band', false);
    }

    $this->get('/kelompok/profil')->assertSee('line-clamp-2 text-[1.5rem] leading-8 font-semibold', false)->assertSee('Laporan terverifikasi');
});

test('detail kelompok Admin: inisial, judul, jenis dan kampung, badge status dan verifikasi, lalu aksi di kanan', function () {
    $this->organization->update(['name' => 'KUB Banner']);

    $html = $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/kelompok/'.$this->organization->id)->assertOk()->getContent();

    expect($html)->not->toContain('overlay-forest')
        ->toMatch('#<h1[^>]*>KUB Banner</h1>\s*<p[^>]*>.*?</p>\s*<div[^>]*aria-label="Status kelompok">\s*<span data-tone="success"#s')
        ->toContain('aria-label="Tindakan lain"')
        ->and(strpos($html, 'Ubah profil'))->toBeGreaterThan(strpos($html, 'aria-label="Status kelompok"'));
});

test('tab detail kelompok di HP diberi penanda geser di tepi kanan', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/kelompok/'.$this->organization->id)->assertOk()
        ->assertSee('x-ref="scroller"', false)
        ->assertSee('bg-linear-to-l from-panel to-transparent md:hidden', false);
});

test('detail kelompok di HP: header atas "Detail kelompok", judul halaman tetap nama kelompok', function () {
    $this->organization->update(['name' => 'KUB Judul']);

    $this->actingAs(User::factory()->admin()->create())->get('/admin/kelompok/'.$this->organization->id)->assertOk()
        ->assertSee('truncate text-ui font-semibold text-ink">Detail kelompok</p>', false)
        ->assertSee('<title>KUB Judul', false);
});
