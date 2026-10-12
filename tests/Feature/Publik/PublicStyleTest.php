<?php

use App\Models\BusinessSector;
use App\Models\HeroSlide;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ImageVariants;
use App\Support\PublicDirectory;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;

beforeEach(function () {
    Storage::fake('local');
});

/** Foto "asli" berpola agar kompresinya realistis. */
function photoFile(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    for ($x = 0; $x < $width; $x += 6) {
        imagefilledrectangle($image, $x, 0, $x + 3, $height, imagecolorallocate($image, ($x * 7) % 255, 120, ($x * 3) % 255));
    }
    for ($i = 0; $i < 4000; $i++) {
        imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
    }
    $path = tempnam(sys_get_temp_dir(), 'foto').'.jpg';
    imagejpeg($image, $path, 92);

    return $path;
}

function heroSection(string $html): string
{
    preg_match('#<section[^>]*data-hero.*?</section>#s', $html, $match);

    return $match[0] ?? '';
}

test('hero tanpa slide memakai foto bawaan dengan overlay', function () {
    $hero = heroSection($this->get('/')->assertOk()->getContent());

    expect($hero)->toContain('/foto-hero/bawaan')->toContain('overlay-hero')->toContain('fetchpriority="high"');
});

test('hero dengan foto memakai WebP srcset, prioritas tinggi, dan overlay; gambar lain lazy-load', function () {
    $path = 'site/sampul-uji.jpg';
    ImageVariants::store('local', photoFile(2400, 1200), $path, ImageVariants::WIDE, ImageVariants::HERO_BUDGET);
    SiteSetting::put(['about_photo_path' => $path, 'profile_body' => 'Profil contoh.']);
    $slide = HeroSlide::factory()->create(['position' => 1]);
    HeroSlide::factory()->create(['position' => 2]);
    $organization = Organization::factory()->public()->create();
    $organization->sectors()->attach(BusinessSector::factory()->create());
    Product::factory()->for($organization)->create(['is_public' => true, 'is_featured' => true, 'photo_path' => 'products/1/a.jpg']);

    $html = $this->get('/')->assertOk()->getContent();
    $hero = heroSection($html);

    expect($hero)->toContain('<source type="image/webp"')
        ->toContain("/foto-hero/{$slide->id}?ukuran=1920&amp;format=webp")
        ->toContain('fetchpriority="high"')
        ->toContain('overlay-hero');

    preg_match_all('/<img\b[^>]*>/', $html, $images);
    $eager = array_filter($images[0], fn ($img) => str_contains($img, 'fetchpriority="high"'));
    $lazy = array_filter($images[0], fn ($img) => str_contains($img, 'loading="lazy"'));
    expect($eager)->toHaveCount(1)->and(count($lazy))->toBe(count($images[0]) - 1);
});

test('varian WebP dibuat per lebar dan hero 1600 px tidak lebih dari 200 KB', function () {
    $path = 'site/sampul-uji.jpg';
    ImageVariants::store('local', photoFile(3000, 1500), $path, ImageVariants::WIDE, ImageVariants::HERO_BUDGET);

    foreach (ImageVariants::WIDE as $width) {
        $variant = Storage::disk('local')->get(ImageVariants::path($path, $width));
        expect(getimagesizefromstring($variant)['mime'])->toBe('image/webp')
            ->and(getimagesizefromstring($variant)[0])->toBe($width);
    }
    expect(strlen(Storage::disk('local')->get(ImageVariants::path($path, 1600))))->toBeLessThanOrEqual(200 * 1024);
});

test('route gambar menyajikan WebP dan kembali ke JPEG bila varian belum ada', function () {
    Storage::disk('local')->put('site/sampul-lama.jpg', file_get_contents(photoFile(800, 400)));
    SiteSetting::put(['cover_photo_path' => 'site/sampul-lama.jpg']);

    $this->get('/foto-sampul?ukuran=960&format=webp')->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    ImageVariants::store('local', Storage::disk('local')->path('site/sampul-lama.jpg'), 'site/sampul-lama.jpg', ImageVariants::WIDE, writeJpeg: false);
    $this->get('/foto-sampul?ukuran=960&format=webp')->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get('/foto-tentang')->assertNotFound();
});

test('kartu bidang usaha: foto bidang, lalu foto produk publik, lalu polos', function () {
    $withPhoto = BusinessSector::factory()->create(['name' => 'Bidang Berfoto', 'photo_path' => 'site/bidang-1.jpg']);
    $fromProduct = BusinessSector::factory()->create(['name' => 'Bidang Produk']);
    $plain = BusinessSector::factory()->create(['name' => 'Bidang Polos']);

    $listed = Organization::factory()->public()->create();
    $listed->sectors()->attach([$withPhoto->id, $fromProduct->id, $plain->id]);
    $publicProduct = Product::factory()->for($listed)->create(['is_public' => true, 'photo_path' => 'products/1/a.jpg']);
    $listed->sectors()->detach($plain->id);
    $other = Organization::factory()->public()->create();
    $other->sectors()->attach($plain);

    // Produk berfoto dari kelompok tidak publik tidak boleh dipakai untuk kartu.
    $hidden = Organization::factory()->create(['is_public' => false]);
    $hidden->sectors()->attach($plain);
    $hiddenProduct = Product::factory()->for($hidden)->create(['is_public' => true, 'photo_path' => 'products/9/b.jpg']);

    $photos = PublicDirectory::sectors()->mapWithKeys(fn ($sector) => [$sector->name => $sector->photo]);

    expect($photos['Bidang Berfoto']['kind'])->toBe('sector')
        ->and($photos['Bidang Produk'])->toMatchArray(['kind' => 'product', 'id' => $publicProduct->id])
        ->and($photos['Bidang Polos'])->toBeNull()
        ->and($hiddenProduct->id)->not->toBe($photos['Bidang Produk']['id']);
    // Beranda: strip bidang usaha berikon; hero tanpa foto bidang (hanya slide Admin atau foto bawaan).
    $html = $this->get('/')->assertOk()->assertSee('Bidang Polos')->getContent();
    expect(heroSection($html))->not->toContain('/foto-bidang/');
});

test('admin mengunggah dan menghapus foto bidang usaha serta foto Tentang', function () {
    $sector = BusinessSector::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->postJson("/admin/konten/foto-bidang/{$sector->id}", ['file' => realUpload('bidang.jpg', file_get_contents(photoFile(1200, 800)))])->assertCreated();
    $path = $sector->fresh()->photo_path;
    expect(Storage::disk('local')->exists(ImageVariants::path($path, 480)))->toBeTrue();
    $this->get("/foto-bidang/{$sector->id}?ukuran=480&format=webp")->assertOk()->assertHeader('Content-Type', 'image/webp');

    $this->delete("/admin/konten/foto-bidang/{$sector->id}")->assertSessionHas('status', 'Foto bidang usaha dihapus.');
    expect($sector->fresh()->photo_path)->toBeNull()->and(Storage::disk('local')->allFiles('site'))->toBe([]);

    $this->postJson('/admin/konten/foto-tentang', ['file' => realUpload('tentang.jpg', file_get_contents(photoFile(1200, 800)))])->assertCreated();
    expect(SiteSetting::get('about_photo_path'))->not->toBeNull();
    $this->actingAs(User::factory()->opd()->create())->postJson("/admin/konten/foto-bidang/{$sector->id}", ['file' => realUpload('b.jpg', imageBytes(10, 10))])->assertForbidden();
});

test('foto produk mendapat varian WebP 400 dan 800 untuk kartu publik', function () {
    $organization = Organization::factory()->public()->create();
    $product = Product::factory()->for($organization)->create(['is_public' => true]);

    $this->actingAs(User::factory()->kelompok($organization)->create())
        ->postJson("/foto-produk/{$product->id}", ['file' => realUpload('produk.jpg', file_get_contents(photoFile(1600, 1200)))])
        ->assertCreated();

    $path = $product->fresh()->photo_path;
    foreach (ImageVariants::PRODUCT as $width) {
        expect(getimagesizefromstring(Storage::disk('local')->get(ImageVariants::path($path, $width)))[0])->toBe($width);
    }
    $this->post('/logout');
    $this->get("/foto-produk/{$product->id}?ukuran=800&format=webp")->assertOk()->assertHeader('Content-Type', 'image/webp');
});

test('perintah simker:varian-foto membuat varian untuk foto lama', function () {
    $organization = Organization::factory()->create();
    Storage::disk('local')->put('products/1/lama.jpg', file_get_contents(photoFile(900, 900)));
    Product::factory()->for($organization)->create(['photo_path' => 'products/1/lama.jpg']);

    $this->artisan('simker:varian-foto')->expectsOutputToContain('Varian dibuat untuk 1 foto.')->assertSuccessful();

    expect(Storage::disk('local')->exists('products/1/lama-400.webp'))->toBeTrue()
        ->and(Storage::disk('local')->exists('products/1/lama-800.webp'))->toBeTrue();
});

test('kartu direktori tanpa foto memakai inisial di latar subtle', function () {
    Organization::factory()->public()->create(['name' => 'Kelompok Contoh Alfa']);

    $this->get('/direktori')->assertSee('KC')->assertSee('bg-subtle', false);
});

test('pita judul profil kelompok berlatar forest polos tanpa foto', function () {
    $organization = Organization::factory()->public()->create(['name' => 'KUB Contoh Pita']);
    Product::factory()->for($organization)->create(['is_public' => true, 'photo_path' => 'products/1/a.jpg']);

    $html = $this->get('/direktori/kub-contoh-pita')->assertOk()->getContent();
    preg_match('#<section class="on-dark[^"]*bg-forest.*?</section>#s', $html, $band);

    expect($band[0])->toContain('KUB Contoh Pita')->not->toContain('<picture')
        ->and($html)->toContain('object-contain');
});

test('token warna publik tidak dipakai di area Admin, OPD, dan Kelompok', function () {
    // Halaman masuk (layouts/auth) memakai panel forest atas permintaan; pengecualian tercatat di CLAUDE.md Bagian 7.
    // Pita/banner forest di header area aplikasi (components/page-header) juga pengecualian Bagian 7.
    $publicViews = ['publik/', 'components/layouts/public.blade.php', 'components/public/', 'components/layouts/auth.blade.php', 'components/page-header.blade.php'];
    $offenders = collect(Finder::create()->files()->in(resource_path('views'))->name('*.blade.php'))
        ->filter(fn ($file) => ! collect($publicViews)->contains(fn ($prefix) => str_starts_with($file->getRelativePathname(), $prefix)))
        ->filter(fn ($file) => preg_match('/\b[a-z:-]*(bg|text|border|ring|outline|divide|from|to|via)-(forest|sun)\b/', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
