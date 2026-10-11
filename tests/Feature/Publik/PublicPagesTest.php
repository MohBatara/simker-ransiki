<?php

use App\Models\BusinessSector;
use App\Models\HeroSlide;
use App\Models\InstitutionType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Village;
use App\Support\Format;
use App\Support\PublicDirectory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

test('angka beranda hanya menghitung yang publik, unik, dan di-cache', function () {
    [$kebun, $olahan] = BusinessSector::factory()->count(2)->create();
    $organization = Organization::factory()->public()->create();
    $organization->sectors()->attach([$kebun->id, $olahan->id]);
    Product::factory()->for($organization)->count(2)->create(['is_public' => true]);
    Product::factory()->for($organization)->create(['is_public' => false]);
    Organization::factory()->create()->sectors()->attach($kebun);

    $stats = PublicDirectory::stats();
    expect($stats['organizations'])->toBe(1)
        ->and($stats['products'])->toBe(2)
        ->and($stats['sectors'])->toBe(2)
        ->and(PublicDirectory::sectors()->pluck('organizations_count')->all())->toBe([1, 1])
        ->and(Cache::has(PublicDirectory::STATS_CACHE_KEY))->toBeTrue();

    // Data baru belum terhitung sampai cache kedaluwarsa (10 menit).
    Organization::factory()->public()->create()->sectors()->attach($kebun);
    expect(PublicDirectory::stats()['organizations'])->toBe(1);
});

test('beranda menampilkan angka, tanggal data, dan bidang usaha', function () {
    $this->travelTo('2026-10-09 10:00:00');
    $sector = BusinessSector::factory()->create(['name' => 'Perkebunan']);
    Organization::factory()->public()->create()->sectors()->attach($sector);

    $this->get('/')
        ->assertOk()
        ->assertSee('kelompok terdaftar')
        ->assertSee('Data diperbarui 9 Oktober 2026')
        ->assertSee('Perkebunan')
        ->assertSee('1 kelompok');
});

test('produk unggulan di beranda hanya yang berfoto, maksimal 4, nama sama tidak berdampingan', function () {
    $organization = Organization::factory()->public()->create();
    foreach (['Abon ikan', 'Ikan asap', 'Ikan asap', 'Kopi bubuk', 'Sagu'] as $i => $name) {
        Product::factory()->for($organization)->create(['name' => $name, 'is_public' => true, 'is_featured' => true, 'photo_path' => "products/{$i}/a.jpg"]);
    }
    Product::factory()->for($organization)->create(['name' => 'Tanpa Foto', 'is_public' => true, 'is_featured' => true]);

    $html = $this->get('/')->assertOk()->getContent();
    preg_match('#id="produk-unggulan".*?</section>#s', $html, $section);
    preg_match_all('#<h3[^>]*><a[^>]*>([^<]+)</a></h3>#', $section[0], $names);

    expect($names[1])->toHaveCount(4)
        ->not->toContain('Tanpa Foto')
        ->and(collect($names[1])->sliding(2)->contains(fn ($pair) => $pair->first() === $pair->last()))->toBeFalse();
});

test('produk unggulan tanpa foto memakai foto bidang usaha dari Admin, atau petak inisial', function () {
    $sector = BusinessSector::factory()->create(['photo_path' => 'site/bidang-1.jpg']);
    $withSector = Organization::factory()->public()->create();
    $withSector->sectors()->attach($sector);
    Product::factory()->for($withSector)->create(['name' => 'Kakao kering', 'is_public' => true, 'is_featured' => true]);
    Product::factory()->for(Organization::factory()->public()->create())->create(['name' => 'Sagu lempeng', 'is_public' => true, 'is_featured' => true]);

    $html = $this->get('/')->assertOk()->getContent();
    preg_match('#id="produk-unggulan".*?</section>#s', $html, $section);

    expect($section[0])->toContain('Kakao kering')->toContain("/foto-bidang/{$sector->id}?ukuran=480&amp;format=webp")
        ->toContain('Sagu lempeng')->toContain('aspect-[16/9]')->toContain('SL');
});

test('halaman produk memakai area inisial kecil 16:9 untuk produk tanpa foto', function () {
    $organization = Organization::factory()->public()->create();
    Product::factory()->for($organization)->create(['name' => 'Kakao kering', 'is_public' => true]);

    $this->get('/produk')->assertOk()->assertSee('aspect-[16/9]', false)->assertSee('KK');
});

test('hero berisi judul dan tombol; tanpa bilah atas, Masuk di header, kontak kantor di footer', function () {
    SiteSetting::put(['office_address' => 'Jalan Contoh 1', 'office_phone' => '0800-1234-5678', 'office_email' => 'kantor@contoh.test']);

    $html = $this->get('/')->assertOk()->getContent();
    preg_match('#<section[^>]*data-hero.*?</section>#s', $html, $hero);

    expect($hero[0])->toContain('Kelembagaan Ekonomi <span class="text-sun">Distrik Ransiki</span>')
        ->toContain('href="'.route('public.directory').'"')
        ->toContain('href="'.route('public.products').'"')
        ->and($html)->not->toContain('aria-label="Tautan atas"')
        ->and(substr($html, strpos($html, '<footer')))->toContain('Jalan Contoh 1')->toContain('tel:080012345678')->toContain('kantor@contoh.test');
    preg_match('#<header.*?</header>#s', $html, $header);
    expect($header[0])->toContain('href="'.route('login').'"')->toContain('Masuk');

    $this->actingAs(User::factory()->admin()->create())->get('/')->assertSee('href="'.route('admin.dashboard').'"', false)->assertSee('Dasbor');
});

test('slider hero memakai slide aktif dari Admin urut posisi, dengan tombol panah dan titik', function () {
    $first = HeroSlide::factory()->create(['position' => 2, 'focus' => 'top', 'alt_text' => 'Alt slide pertama']);
    HeroSlide::factory()->create(['position' => 1]);
    $hidden = HeroSlide::factory()->inactive()->create(['position' => 3]);

    $html = $this->get('/')->assertOk()->getContent();
    preg_match('#<section[^>]*data-hero.*?</section>#s', $html, $hero);

    expect($hero[0])->toContain('heroSlider(2)')
        ->toContain('Foto sebelumnya')->toContain('Foto berikutnya')->toContain('Foto 2 dari 2')
        ->toContain("/foto-hero/{$first->id}?ukuran=1920&amp;format=webp")
        ->toContain('alt="Alt slide pertama"')->toContain('object-top')
        ->not->toContain("/foto-hero/{$hidden->id}")
        ->not->toContain('/foto-hero/bawaan')
        ->and(substr_count($hero[0], '<picture'))->toBe(2)
        ->and(substr_count($hero[0], 'fetchpriority="high"'))->toBe(1);
});
test('tanpa slide aktif, hero memakai satu foto bawaan tanpa kontrol slider (bukan foto produk)', function () {
    HeroSlide::factory()->inactive()->create();
    $organization = Organization::factory()->public()->create();
    $product = Product::factory()->for($organization)->create(['is_public' => true, 'is_featured' => true, 'photo_path' => 'products/1/a.jpg']);

    $html = $this->get('/')->assertOk()->assertDontSee('heroSlider')->assertDontSee('Foto berikutnya')->getContent();
    preg_match('#<section[^>]*data-hero.*?</section>#s', $html, $hero);

    expect($hero[0])->toContain('/foto-hero/bawaan?ukuran=1280&amp;format=webp')->not->toContain('/foto-produk/');
    $this->get('/foto-hero/bawaan?ukuran=640&format=webp')->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get('/foto-hero/bawaan')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});
test('angka Tentang dan jenis kelembagaan hanya dari kelompok publik', function () {
    $type = InstitutionType::factory()->create(['name' => 'KUB']);
    [$a, $b] = Village::factory()->count(2)->create();
    $listed = Organization::factory()->public()->create(['institution_type_id' => $type->id, 'village_id' => $a->id]);
    Organization::factory()->public()->create(['institution_type_id' => $type->id, 'village_id' => $a->id]);
    Organization::factory()->create(['is_public' => false, 'institution_type_id' => $type->id, 'village_id' => $b->id]);
    Organization::factory()->public()->create(['status' => 'closed', 'village_id' => $b->id]);
    Product::factory()->for($listed)->create(['is_public' => true, 'is_featured' => true]);
    Product::factory()->for($listed)->create(['is_public' => false, 'is_featured' => true]);

    $stats = PublicDirectory::stats();
    expect($stats['organizations'])->toBe(2)->and($stats['villages'])->toBe(1)->and($stats['featured'])->toBe(1)
        ->and(PublicDirectory::institutionTypes()->firstWhere('name', 'KUB')->organizations_count)->toBe(2);

    $this->get('/')->assertSee('Jenis kelembagaan')->assertSee('/direktori?jenis='.$type->id, false);
});

test('keterangan slide tampil di pojok kanan bawah hero', function () {
    HeroSlide::factory()->create(['caption' => 'Bidang usaha perikanan']);

    $this->get('/')->assertSee('Bidang usaha perikanan')->assertSee('justify-end', false);
});

test('judul dan kalimat penjelas hero bisa diubah Admin, kosong kembali ke bawaan', function () {
    $this->get('/')->assertSee('Kelembagaan Ekonomi <span class="text-sun">Distrik Ransiki</span>', false);

    SiteSetting::put(['hero_title' => 'Usaha Kampung', 'hero_title_accent' => 'Ransiki', 'home_intro' => 'Kalimat contoh.']);

    $this->get('/')->assertSee('Usaha Kampung <span class="text-sun">Ransiki</span>', false)->assertSee('Kalimat contoh.');
});
test('foto sampul tidak lagi menjadi hero beranda; tetap dipakai pita Profil Ransiki', function () {
    $this->get('/profil')->assertDontSee('/foto-sampul');

    SiteSetting::put(['cover_photo_path' => 'site/cover.jpg']);

    preg_match('#<section[^>]*data-hero.*?</section>#s', $this->get('/')->getContent(), $hero);
    expect($hero[0])->not->toContain('/foto-sampul');
    $this->get('/profil')->assertSee('/foto-sampul?v=', false);
});
test('direktori mencari nama kelompok dan nama produk serta memfilter jenis, bidang, dan kampung', function (string $query, array $visible) {
    $type = InstitutionType::factory()->create();
    $village = Village::factory()->create();
    $sector = BusinessSector::factory()->create();
    $alfa = Organization::factory()->public()->create(['name' => 'Kelompok Contoh Alfa', 'institution_type_id' => $type->id, 'village_id' => $village->id]);
    $alfa->sectors()->attach($sector);
    Product::factory()->for($alfa)->create(['name' => 'Sagu lempeng', 'is_public' => true]);
    $beta = Organization::factory()->public()->create(['name' => 'Kelompok Contoh Beta']);
    Product::factory()->for($beta)->create(['name' => 'Gula aren', 'is_public' => false]);

    $response = $this->get('/direktori?'.strtr($query, ['{jenis}' => $type->id, '{bidang}' => $sector->id, '{kampung}' => $village->id]))->assertOk();

    foreach (['Kelompok Contoh Alfa', 'Kelompok Contoh Beta'] as $name) {
        in_array($name, $visible) ? $response->assertSee($name) : $response->assertDontSee($name);
    }
})->with([
    'nama' => ['q=beta', ['Kelompok Contoh Beta']],
    'produk publik' => ['q=sagu', ['Kelompok Contoh Alfa']],
    'produk tidak publik tidak ikut dicari' => ['q=gula', []],
    'jenis' => ['jenis={jenis}', ['Kelompok Contoh Alfa']],
    'bidang' => ['bidang={bidang}', ['Kelompok Contoh Alfa']],
    'kampung' => ['kampung={kampung}', ['Kelompok Contoh Alfa']],
]);

test('meta Open Graph memakai URL absolut dan foto produk publik', function () {
    $organization = Organization::factory()->public()->create(['name' => 'KUB Contoh Meta', 'description' => 'Deskripsi contoh kelompok.']);

    $this->get('/direktori/kub-contoh-meta')
        ->assertSee('<meta property="og:title" content="KUB Contoh Meta · '.config('app.name').'">', false)
        ->assertSee('<meta property="og:description" content="Deskripsi contoh kelompok.">', false)
        ->assertSee('<meta property="og:image" content="'.asset('og-default.png').'">', false)
        ->assertSee('<link rel="canonical" href="'.url('/direktori/kub-contoh-meta').'">', false);

    $product = Product::factory()->for($organization)->create(['is_public' => true, 'photo_path' => 'products/9/foto.jpg']);

    $this->get('/direktori/kub-contoh-meta')->assertSee('<meta property="og:image" content="'.$product->photoUrl().'">', false);
});

test('sitemap berisi XML valid dan robots menolak area login', function () {
    Organization::factory()->public()->create(['name' => 'KUB Contoh Peta']);

    $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = simplexml_load_string($sitemap->getContent());
    expect($xml)->not->toBeFalse()
        ->and(collect($xml->url)->map(fn ($url) => (string) $url->loc)->all())->toContain(url('/direktori/kub-contoh-peta'));

    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Disallow: /lampiran')->assertSee('Sitemap: '.url('/sitemap.xml'));
    $this->get('/login')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $this->get('/')->assertDontSee('noindex');
});

test('header publik memakai tautan teks Masuk dan semua layout punya tautan lewati ke konten', function () {
    $this->get('/')->assertSee('Lewati ke konten')->assertSee('href="#konten"', false)->assertSee('id="konten"', false)
        ->assertSee('Dikelola oleh Pemerintah Distrik Ransiki');
    $this->get('/login')->assertSee('Lewati ke konten');
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertSee('Lewati ke konten');
});

test('tautan WhatsApp dari nomor Indonesia', function (string $phone, ?string $url) {
    expect(Format::whatsappUrl($phone))->toBe($url);
})->with([
    ['0812-3456-7890', 'https://wa.me/6281234567890'],
    ['+62 812 3456 7890', 'https://wa.me/6281234567890'],
    ['812 3456 7890', 'https://wa.me/6281234567890'],
    ['', null],
]);

test('Konten Website hanya untuk admin', function (string $role) {
    $this->actingAs($role === 'opd' ? User::factory()->opd()->create() : User::factory()->create());

    $this->get('/admin/konten')->assertForbidden();
    $this->put('/admin/konten', ['home_intro' => 'Diubah'])->assertForbidden();
    expect(SiteSetting::get('home_intro'))->toBeNull();
})->with(['opd', 'kelompok']);

test('admin mengubah konten dan hasilnya langsung tampil di website publik', function () {
    $this->get('/kontak'); // mengisi cache footer lebih dulu
    $this->actingAs(User::factory()->admin()->create());

    $this->put('/admin/konten', [
        'home_intro' => 'Pengantar contoh beranda.',
        'profile_body' => "## Sejarah contoh\n\nParagraf contoh dengan **teks tebal**.\n\n<script>alert(1)</script>",
        'office_phone' => '0800-1234-5678',
        'office_whatsapp' => '0812-0000-1111',
        'office_email' => 'kantor@contoh.test',
    ])->assertSessionHas('status', 'Konten website disimpan.');

    $this->get('/')->assertSee('Pengantar contoh beranda.')->assertSee('kantor@contoh.test');
    $this->get('/profil')
        ->assertSee('<h2>Sejarah contoh</h2>', false)
        ->assertSee('<strong>teks tebal</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false);
    $this->get('/kontak')->assertSee('https://wa.me/6281200001111', false)->assertSee('0800-1234-5678');
});

test('validasi Konten Website berbahasa Indonesia', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/admin/konten', ['office_email' => 'bukan-email', 'home_intro' => str_repeat('a', 301)])
        ->assertSessionHasErrors([
            'office_email' => 'Email kantor harus berupa alamat email yang valid.',
            'home_intro' => 'Pengantar beranda paling banyak 300 karakter.',
        ]);
});

test('foto sampul diunggah, diproses, tampil publik, lalu bisa dihapus', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->admin()->create());

    $this->postJson('/admin/konten/foto-sampul', ['file' => realUpload('sampul.jpg', jpegWithExif(3200, 1400, orientation: 1))])->assertCreated();

    $path = SiteSetting::get('cover_photo_path');
    $stored = Storage::disk('local')->get($path);
    expect(getimagesizefromstring($stored)[0])->toBe(1600)->and(exifOf($stored))->not->toHaveKey('GPSLatitudeRef');

    $this->post('/logout');
    $this->get('/foto-sampul')->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    $this->actingAs(User::factory()->admin()->create())->delete('/admin/konten/foto-sampul')->assertSessionHas('status', 'Foto sampul dihapus.');
    expect(Storage::disk('local')->exists($path))->toBeFalse();
    $this->get('/foto-sampul')->assertNotFound();
});

test('ringkasan beranda tetap terbaca dari cache yang benar-benar diserialisasi', function () {
    // Cache di tes biasanya array (tanpa serialisasi); di server memakai database yang menolak objek.
    config(['cache.default' => 'file']);
    Cache::flush();
    $organization = Organization::factory()->public()->create();
    $organization->sectors()->attach(BusinessSector::factory()->create(['name' => 'Perikanan']));

    PublicDirectory::stats();
    PublicDirectory::sectors();

    expect(PublicDirectory::stats()['updated_at'])->toBeInstanceOf(Illuminate\Support\Carbon::class)
        ->and(PublicDirectory::sectors()->first()->name)->toBe('Perikanan');
    $this->get('/')->assertOk()->assertSee('Perikanan');

    Cache::flush();
});

test('ikon bidang usaha dari Lucide menurut slug nama, bidang baru memakai ikon bawaan', function (string $sector, string $icon) {
    expect(App\Support\PublicIcons::sector($sector))->toBe($icon)
        ->and(file_exists(resource_path('svg/lucide/'.substr($icon, 7).'.svg')))->toBeTrue();
})->with([
    ['Pertanian', 'lucide:sprout'],
    ['Perkebunan', 'lucide:trees'],
    ['Perikanan', 'lucide:fish'],
    ['Pengolahan Hasil', 'lucide:cooking-pot'],
    ['UMKM', 'lucide:store'],
    ['Lainnya', 'lucide:box'],
    ['Pariwisata', 'lucide:box'],
]);

test('ikon Lucide dirender sebaris dengan garis setebal Heroicons', function () {
    Organization::factory()->public()->create()->sectors()->attach(BusinessSector::factory()->create(['name' => 'Perikanan']));

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('stroke-width="1.5"')->toContain('M6.5 12c.94-3.46 4.94-6 8.5-6');
});
