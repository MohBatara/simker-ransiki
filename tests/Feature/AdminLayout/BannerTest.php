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

test('pita forest di halaman biasa mulai tablet, bukan di halaman form', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/kelompok')->assertSee('app-band md:overflow-hidden md:rounded-card md:bg-forest', false);
    $this->get('/admin/kelompok/tambah')->assertDontSee('app-band md:overflow-hidden', false);
});

test('dasbor memakai banner foto sampul bila ada, dengan varian WebP', function () {
    SiteSetting::put(['cover_photo_path' => 'site/sampul.jpg']);

    $this->actingAs(User::factory()->opd(Opd::factory()->create())->create())->get('/opd')
        ->assertOk()
        ->assertSee('app-band-photo max-md:relative', false)
        ->assertSee('overlay-forest', false)
        ->assertSee('fetchpriority="high"', false)
        ->assertSee('format=webp', false);
});

test('dasbor tanpa foto sampul memakai pita polos', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')
        ->assertOk()
        ->assertDontSee('app-band-photo max-md:relative', false)
        ->assertSee('app-band md:overflow-hidden', false);
});

test('banner detail kelompok di HP: foto hanya di blok judul, tag dan aksi di bawahnya', function () {
    SiteSetting::put(['cover_photo_path' => 'site/sampul.jpg']);
    $this->organization->update(['name' => 'KUB Banner']);

    $html = $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/kelompok/'.$this->organization->id)->assertOk()->getContent();

    $header = substr($html, strpos($html, 'app-band md:relative'));
    $photoBlock = substr($header, 0, strpos($header, 'aria-label="Jenis dan bidang usaha"'));

    expect($photoBlock)->toContain('app-band-photo max-md:relative')
        ->toContain('overlay-forest')
        ->toContain('KUB Banner')
        // Tag dan tombol Ubah profil berada setelah blok foto, bukan di dalamnya.
        ->and(strpos($header, 'Ubah profil'))->toBeGreaterThan(strpos($header, 'aria-label="Jenis dan bidang usaha"'));
});

test('tab detail kelompok di HP diberi penanda geser di tepi kanan', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/kelompok/'.$this->organization->id)->assertOk()
        ->assertSee('x-ref="scroller"', false)
        ->assertSee('bg-linear-to-l from-canvas to-transparent md:hidden', false);
});

test('detail kelompok di HP: header atas "Detail kelompok", banner lebih pendek; dasbor tidak berubah', function () {
    SiteSetting::put(['cover_photo_path' => 'site/sampul.jpg']);
    $this->organization->update(['name' => 'KUB Judul']);
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/kelompok/'.$this->organization->id)->assertOk()
        ->assertSee('truncate text-ui font-semibold text-ink">Detail kelompok</p>', false)
        ->assertSee('<title>KUB Judul', false)
        ->assertSee('max-md:min-h-24 max-md:py-4', false)
        ->assertDontSee('max-md:min-h-32', false);

    $this->get('/admin')->assertOk()->assertSee('max-md:min-h-32 max-md:py-5', false);
});
