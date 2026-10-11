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
        ->assertSee('app-band-photo min-h-40', false)
        ->assertSee('overlay-forest', false)
        ->assertSee('fetchpriority="high"', false)
        ->assertSee('format=webp', false);
});

test('dasbor tanpa foto sampul memakai pita polos', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')
        ->assertOk()
        ->assertDontSee('app-band-photo min-h-40', false)
        ->assertSee('app-band md:overflow-hidden', false);
});
