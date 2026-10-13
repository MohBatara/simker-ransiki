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
        ->assertSeeText('Mengenal Distrik Ransiki')
        ->assertSeeInOrder(['Kelompok', 'Kampung', 'Produk unggulan', 'Bidang usaha'])
        ->assertSee('Data per 9 Oktober 2026')
        ->assertSee('Baca profil lengkap')
        ->assertDontSee('Selengkapnya')
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
        ->toContain('Sagu lempeng')->toContain('aspect-[4/3]')->toContain('SL');
});

test('halaman produk memakai petak inisial 4:3 untuk produk tanpa foto', function () {
    $organization = Organization::factory()->public()->create();
    Product::factory()->for($organization)->create(['name' => 'Kakao kering', 'is_public' => true]);

    $this->get('/produk')->assertOk()->assertSee('aspect-[4/3]', false)->assertSee('KK');
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

test('judul bagian Tentang bisa diubah Admin, kosong kembali ke bawaan', function () {
    $this->actingAs(User::factory()->admin()->create())->put('/admin/konten', ['about_title' => 'Sekilas Ransiki'])->assertSessionHasNoErrors();
    $this->get('/')->assertSeeText('Sekilas Ransiki')->assertDontSeeText('Mengenal Distrik Ransiki');

    $this->put('/admin/konten', ['about_title' => '']);
    $this->get('/')->assertSeeText('Mengenal Distrik Ransiki');
});

test('halaman publik tanpa tanda pisah dan tanpa hiasan garis emas; emas hanya untuk tombol utama dan aksen judul hero', function () {
    $organization = Organization::factory()->public()->create(['name' => 'KUB Contoh Rapi']);
    $organization->sectors()->attach(BusinessSector::factory()->create(['name' => 'Perikanan']));
    Product::factory()->for($organization)->create(['is_public' => true, 'is_featured' => true]);

    foreach (['/', '/direktori', '/direktori/kub-contoh-rapi', '/produk', '/berita', '/profil', '/kontak'] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        $visible = preg_replace('#<(script|style)\b.*?</\1>|<!--.*?-->|<[^>]+>#s', ' ', $html);

        expect($visible)->not->toMatch('/[\x{2013}\x{2014}]/u')
            ->and($html)->not->toContain('h-0.5 w-10 bg-sun')->not->toContain('h-0.5 w-6 bg-sun');

        preg_match_all('/class="([^"]*\b(?:bg|text|border)-sun\b[^"]*)"/', $html, $uses);
        foreach ($uses[1] as $classes) {
            // Tombol utama (x-public.button sun) atau aksen judul hero (span text-sun).
            expect($classes === 'text-sun' || str_contains($classes, 'bg-sun text-ink'))->toBeTrue("Emas dipakai di luar tombol/aksen: {$classes}");
        }
    }

    expect(SiteSetting::DEFAULTS)->each(fn ($value) => $value->not->toMatch('/[\x{2013}\x{2014}]|\b(\w+)-\1\b/u'));
});

test('bagian ajakan memuat empat langkah (teks Konten Website) di latar terang, tanpa panel Cara kerja terpisah', function () {
    Organization::factory()->public()->create()->sectors()->attach(BusinessSector::factory()->create());

    $html = $this->get('/')->assertOk()->getContent();
    preg_match('#aria-labelledby="ajakan".*?</section>#s', $html, $cta);

    // Alur beranda: Mengenal, Jenis kelembagaan, Produk/Berita (bila ada), ajakan, footer.
    expect(strpos($html, 'id="tentang"'))->toBeLessThan(strpos($html, 'id="jenis-kelembagaan"'))
        ->and(strpos($html, 'id="jenis-kelembagaan"'))->toBeLessThan(strpos($html, 'id="ajakan"'))
        ->and(strpos($html, 'id="ajakan"'))->toBeLessThan(strpos($html, '<footer'))
        ->and($html)->not->toContain('id="cara-kerja"')->not->toContain('Cara kerja SIM-KER')
        ->and($cta[0])->toContain('bg-cream')->not->toContain('bg-forest px')
        ->toContain('Pendaftaran dilakukan melalui Kantor Distrik Ransiki. Begini alurnya.')
        ->toContain('Hubungi Kantor Distrik')->toContain('href="'.route('public.contact').'"')
        ->and(strip_tags($cta[0]))->toMatch('/01\s+Langkah 1: Didaftarkan distrik\s+Kantor distrik mendata kelompok dan membuatkan akun\./')
        ->toContain('04')->toContain('Diverifikasi OPD');

    $this->actingAs(User::factory()->admin()->create())
        ->put('/admin/konten', ['how_2_title' => 'Isi profil', 'how_2_text' => 'Kalimat contoh langkah dua.'])
        ->assertSessionHasNoErrors();
    $this->get('/admin/konten')->assertSee('Alur pendaftaran')->assertSee('value="Isi profil"', false)->assertDontSee('Judul panel');

    $this->get('/')->assertSee('Isi profil')->assertSee('Kalimat contoh langkah dua.')->assertSee('Melapor tiap bulan');
});


test('admin mengisi deskripsi jenis kelembagaan di Data Master dan langsung tampil di beranda', function () {
    $type = InstitutionType::factory()->create(['name' => 'KDMP']);
    Organization::factory()->public()->create(['institution_type_id' => $type->id]);
    $this->get('/'); // mengisi cache jenis kelembagaan

    $this->actingAs(User::factory()->admin()->create())
        ->put("/admin/data-master/jenis-kelembagaan/{$type->id}", ['name' => 'KDMP', 'code' => $type->code, 'description' => 'Deskripsi contoh untuk kartu.'])
        ->assertSessionHas('status');

    expect($type->fresh()->description)->toBe('Deskripsi contoh untuk kartu.');
    $this->get('/')->assertSee('Deskripsi contoh untuk kartu.');
    $this->get('/admin/data-master/jenis-kelembagaan')->assertSee('Deskripsi singkat');
});

test('keterangan dan kredit foto publik tanpa catatan internal (data demo)', function () {
    $this->seed(Database\Seeders\DemoSeeder::class);

    foreach (['/', '/profil', '/login'] as $url) {
        $this->get($url)->assertOk()->assertDontSee('Foto sementara')->assertDontSee('foto resmi distrik')->assertDontSee('Dokumentasi sementara');
    }
});

test('jenis kelembagaan: daftar urut terbanyak, hanya jenis publik, tiap baris menaut ke direktori, deskripsi utuh', function () {
    $kub = InstitutionType::factory()->create(['name' => 'KUB', 'description' => 'Deskripsi lengkap jenis yang tidak boleh dipotong sama sekali.']);
    $bumdes = InstitutionType::factory()->create(['name' => 'BUMDes', 'description' => null]);
    $hidden = InstitutionType::factory()->create(['name' => 'Jenis Tersembunyi']);
    Organization::factory()->public()->count(2)->create(['institution_type_id' => $bumdes->id]);
    Organization::factory()->public()->create(['institution_type_id' => $kub->id]);
    Organization::factory()->create(['is_public' => false, 'institution_type_id' => $hidden->id]);

    $html = $this->get('/')->assertOk()->getContent();
    preg_match('#aria-labelledby="jenis-kelembagaan".*?</section>#s', $html, $section);
    preg_match_all('#<li class="border-t border-line">\s*<a\s+href="([^"]+)"#', $section[0], $rows);

    expect($rows[1])->toBe([route('public.directory', ['jenis' => $bumdes->id]), route('public.directory', ['jenis' => $kub->id])])
        ->and($section[0])->toContain('Deskripsi lengkap jenis yang tidak boleh dipotong sama sekali.')
        ->toContain('Lihat semua kelompok')->toContain('href="'.route('public.directory').'"')
        ->toContain('x-data="{ active: 0 }"')->toContain('lg:sticky')
        // Pergantian foto hanya di desktop: di HP foto pertama tidak pernah disembunyikan setelah baris disentuh.
        ->toContain("x-bind:class=\"{ 'lg:opacity-0': active !== 0 }\"")->not->toContain("'opacity-0': active")
        ->not->toContain('Jenis Tersembunyi')->not->toContain('line-clamp')->not->toContain('...');
});

test('jenis kelembagaan: tanpa kartu warna, avatar, ikon tekstur; tidak ada konten yang disembunyikan server', function () {
    Organization::factory()->public()->count(3)->create(['institution_type_id' => InstitutionType::factory()->create()->id]);

    preg_match('#aria-labelledby="jenis-kelembagaan".*?</section>#s', $this->get('/')->getContent(), $section);

    expect($section[0])->not->toMatch('/\bbg-(forest|clay|sand|sage|cream) text-/')
        ->not->toContain('ring-2')->not->toContain('opacity-[0.08]')->not->toContain('data-reveal-state')->not->toContain('mask-fade');
});

test('foto jenis: foto jenis, lalu foto bidang dominan, lalu foto bawaan; foto pertama tidak lazy, sisanya lazy', function () {
    $withPhoto = InstitutionType::factory()->create(['name' => 'KUB', 'photo_path' => 'site/jenis-kub.jpg']);
    $bySector = InstitutionType::factory()->create(['name' => 'Kelompok Perikanan']);
    $plain = InstitutionType::factory()->create(['name' => 'Lainnya']);
    $fish = BusinessSector::factory()->create(['name' => 'Perikanan', 'photo_path' => 'site/bidang-ikan.jpg']);
    $trade = BusinessSector::factory()->create(['name' => 'Perdagangan', 'photo_path' => 'site/bidang-dagang.jpg']);
    Organization::factory()->public()->count(3)->create(['institution_type_id' => $withPhoto->id]);
    foreach ([$fish, $fish, $trade] as $sector) {
        Organization::factory()->public()->create(['institution_type_id' => $bySector->id])->sectors()->attach($sector);
    }
    Organization::factory()->public()->create(['institution_type_id' => $plain->id]);

    preg_match('#aria-labelledby="jenis-kelembagaan".*?</section>#s', $this->get('/')->getContent(), $section);
    [$panel, $list] = explode('<ul', $section[0], 2);
    preg_match_all('#<img\b[^>]*>#', $panel, $images);
    preg_match_all('#<picture\b.*?</picture>#s', $list, $thumbs);

    // Panel desktop.
    expect($images[0])->toHaveCount(3)
        ->and($images[0][0])->toContain("/foto-jenis/{$withPhoto->id}")->not->toContain('loading="lazy"')->not->toContain('fetchpriority')
        ->and($images[0][1])->toContain("/foto-bidang/{$fish->id}")->toContain('loading="lazy"')
        ->and($images[0][2])->toContain('/foto-hero/bawaan')->toContain('loading="lazy"')
        ->and($section[0])->not->toContain("/foto-bidang/{$trade->id}")->not->toContain('/foto-produk/');
    // Foto kecil HP/tablet: urutan prioritas sama, lazy, sizes 72px (browser memilih varian terkecil: 640, foto bidang 480).
    expect($thumbs[0])->toHaveCount(3)
        ->and($thumbs[0][0])->toContain("/foto-jenis/{$withPhoto->id}?ukuran=640")->toContain('sizes="72px"')->toContain('loading="lazy"')->toContain('lg:hidden')
        ->and($thumbs[0][1])->toContain("/foto-bidang/{$fish->id}?ukuran=480")
        ->and($thumbs[0][2])->toContain('/foto-hero/bawaan?ukuran=640');
});

test('jenis kelembagaan di HP/tablet: panel foto disembunyikan, baris berisi jumlah kelompok, tombol di bawah daftar', function () {
    Organization::factory()->public()->count(7)->create(['institution_type_id' => InstitutionType::factory()->create(['name' => 'UMKM'])->id]);

    preg_match('#aria-labelledby="jenis-kelembagaan".*?</section>#s', $this->get('/')->getContent(), $section);

    expect($section[0])->toContain('max-lg:hidden lg:sticky')
        ->toContain('<span class="font-semibold tabular-nums">7</span> kelompok')
        ->toContain('md:grid md:grid-cols-2')
        ->toMatch('#</ul>\s*</div>\s*<a href="'.preg_quote(route('public.directory'), '#').'"[^>]*lg:hidden[^>]*>\s*Lihat semua kelompok#');
});

test('kartu produk: label bidang, judul 2 baris, kelompok dan kampung satu baris, kapasitas di dasar, tanpa lencana ikon', function () {
    $organization = Organization::factory()->public()->create(['name' => 'Kelompok Tani Padi Sawah Sri Rejeki']);
    $organization->sectors()->attach(BusinessSector::factory()->create(['name' => 'Pertanian']));
    Product::factory()->for($organization)->create(['name' => 'Beras', 'capacity' => '1.200 kg/bulan', 'is_public' => true, 'is_featured' => true, 'photo_path' => 'products/1/a.jpg']);
    Product::factory()->for($organization)->create(['name' => 'Gabah', 'capacity' => null, 'is_public' => true, 'is_featured' => true]);

    preg_match('#id="produk-unggulan".*?</section>#s', $this->get('/')->getContent(), $section);
    preg_match_all('#<article\b.*?</article>#s', $section[0], $cards);

    expect($cards[0])->toHaveCount(2)
        ->and($cards[0][0])->toContain('text-clay uppercase">Pertanian</p>')
        ->toContain('line-clamp-2 min-h-[3.25rem]')
        ->toContain('title="Kelompok Tani Padi Sawah Sri Rejeki"')
        ->toContain('Kapasitas')->toContain('1.200 kg/bulan')->toContain('mt-auto')
        ->toContain('group-hover:scale-[1.04]')->toContain('after:absolute after:inset-0')
        ->not->toContain('icon-badge')->not->toContain('-bottom-6')
        ->and(substr_count($cards[0][0], '<a '))->toBe(1)
        ->and($cards[0][1])->toContain('Belum diisi');
});

test('bagian Produk unggulan di Beranda: tanpa label kecil, tombol pil, geser di HP dengan titik penanda', function () {
    $organization = Organization::factory()->public()->create();
    Product::factory()->for($organization)->count(3)->create(['is_public' => true, 'is_featured' => true]);

    preg_match('#aria-labelledby="produk-unggulan".*?</section>#s', $this->get('/')->getContent(), $section);

    expect($section[0])->not->toContain('uppercase tracking-wider text-brand')
        ->toContain('x-data="scrollDots"')->toContain('w-[calc(88vw-28px)]')->toContain('md:grid-cols-2')->toContain('lg:grid-cols-4')
        ->toContain('Kartu 3 dari 3')
        ->and(substr_count($section[0], 'Lihat semua produk'))->toBe(2)
        ->and($section[0])->toContain('rounded-full bg-forest');
});

