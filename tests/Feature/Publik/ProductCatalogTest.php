<?php

use App\Enums\OrganizationStatus;
use App\Enums\ProfileStatus;
use App\Models\Attachment;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Village;
use Illuminate\Support\Facades\Storage;

/** Nama produk di grid katalog (tautan kartu ke detail produk), sesuai urutan tampil. */
function catalogCards(string $html): array
{
    preg_match('#<ul data-cards.*?</ul>#s', $html, $grid);
    preg_match_all('#<a href="[^"]*/produk/[^"]+" title="([^"]+)"#', $grid[0] ?? '', $matches);

    return $matches[1];
}

function sectorCount(string $html, string $key): ?int
{
    return preg_match('#data-facet="bidang-'.$key.'"><span class="sr-only">, </span>([\d.]+)<#', $html, $match) ? (int) str_replace('.', '', $match[1]) : null;
}

beforeEach(function () {
    $this->kebun = BusinessSector::factory()->create(['name' => 'Perkebunan']);
    $this->ikan = BusinessSector::factory()->create(['name' => 'Perikanan']);
    $this->satu = Village::factory()->create(['name' => 'Kampung Contoh 1']);
    $this->dua = Village::factory()->create(['name' => 'Kampung Contoh 2']);

    $make = function (string $name, Village $village, array $sectors, array $extra = []) {
        $organization = Organization::factory()->public()->create(['name' => $name, 'village_id' => $village->id, ...$extra]);
        $organization->sectors()->attach(collect($sectors)->pluck('id'));

        return $organization;
    };

    // Alfa berbidang ganda: produknya tidak boleh terhitung ganda di pil "Semua".
    $this->alfa = $make('Kelompok Contoh Alfa', $this->satu, [$this->kebun, $this->ikan]);
    $this->beta = $make('Kelompok Contoh Beta', $this->dua, [$this->ikan]);
    $this->rehat = $make('Kelompok Contoh Rehat', $this->satu, [$this->kebun], ['status' => OrganizationStatus::Inactive]);
    $closed = $make('Kelompok Contoh Tutup', $this->satu, [$this->kebun], ['status' => OrganizationStatus::Closed]);
    $private = $make('Kelompok Contoh Privat', $this->satu, [$this->kebun], ['is_public' => false]);

    $this->kakao = Product::factory()->for($this->alfa)->create(['name' => 'Kakao kering', 'is_public' => true, 'is_featured' => true, 'capacity' => '200 kg/bulan']);
    $this->ikanAsap = Product::factory()->for($this->beta)->create(['name' => 'Ikan asap', 'is_public' => true]);
    $this->kopi = Product::factory()->for($this->rehat)->create(['name' => 'Kopi bubuk', 'is_public' => true]);
    Product::factory()->for($this->alfa)->create(['name' => 'Produk Draf', 'is_public' => false]);
    Product::factory()->for($closed)->create(['name' => 'Produk Kelompok Tutup', 'is_public' => true, 'is_featured' => true]);
    Product::factory()->for($private)->create(['name' => 'Produk Kelompok Privat', 'is_public' => true]);
});

test('katalog hanya menampilkan produk publik dari kelompok publik (aktif atau tidak aktif sementara)', function () {
    $html = $this->get('/produk')->assertOk()->getContent();

    expect(catalogCards($html))->toBe(['Kakao kering', 'Ikan asap', 'Kopi bubuk'])
        ->and($html)->not->toContain('Produk Draf')->not->toContain('Produk Kelompok Tutup')->not->toContain('Produk Kelompok Privat')
        ->and(strip_tags($html))->toMatch('/3\s+produk ditemukan/');
});

test('filter bidang, kampung, dan pencarian (nama produk, nama kelompok, kampung)', function (string $query, array $expected) {
    $query = strtr($query, ['{kebun}' => $this->kebun->id, '{ikan}' => $this->ikan->id, '{satu}' => $this->satu->id, '{dua}' => $this->dua->id]);

    expect(catalogCards($this->get('/produk?'.$query)->assertOk()->getContent()))->toBe($expected);
})->with([
    'bidang' => ['bidang={ikan}', ['Kakao kering', 'Ikan asap']],
    'kampung' => ['kampung={satu}', ['Kakao kering', 'Kopi bubuk']],
    'bidang dan kampung' => ['bidang={ikan}&kampung={dua}', ['Ikan asap']],
    'cari nama produk' => ['q=kopi', ['Kopi bubuk']],
    'cari nama kelompok' => ['q=beta', ['Ikan asap']],
    'cari kampung' => ['q=contoh 2', ['Ikan asap']],
    'produk privat tidak ikut dicari' => ['q=draf', []],
    'nilai tidak sah diabaikan' => ['bidang[]=1&kampung=abc&urut=acak', ['Kakao kering', 'Ikan asap', 'Kopi bubuk']],
]);

test('urutan: Pilihan distrik dulu (bawaan), Terbaru, Nama A ke Z', function () {
    $this->ikanAsap->forceFill(['created_at' => now()->addDay()])->saveQuietly();

    expect(catalogCards($this->get('/produk')->getContent())[0])->toBe('Kakao kering')
        ->and(catalogCards($this->get('/produk?urut=terbaru')->getContent())[0])->toBe('Ikan asap')
        ->and(catalogCards($this->get('/produk?urut=nama')->getContent()))->toBe(['Ikan asap', 'Kakao kering', 'Kopi bubuk']);
});

test('pil bidang: jumlah produk unik, hanya produk publik, mengikuti pencarian dan kampung', function () {
    $html = $this->get('/produk')->getContent();
    expect(sectorCount($html, 'semua'))->toBe(3)
        ->and(sectorCount($html, (string) $this->kebun->id))->toBe(2) // Kakao (Alfa) + Kopi (Rehat); produk Tutup/Privat tidak
        ->and(sectorCount($html, (string) $this->ikan->id))->toBe(2)
        ->and($html)->toMatch('#name="bidang"\s+value=""\s+checked#');

    $html = $this->get("/produk?kampung={$this->dua->id}&bidang={$this->ikan->id}")->getContent();
    expect(sectorCount($html, 'semua'))->toBe(1)
        ->and(sectorCount($html, (string) $this->kebun->id))->toBe(0)
        ->and($html)->toMatch('#name="bidang"\s+value="'.$this->ikan->id.'"\s+checked#');
});

test('deretan Pilihan distrik hanya di halaman 1 tanpa filter, maks 8, tanpa produk kelompok tertutup', function () {
    Product::factory()->for($this->beta)->count(9)->create(['is_public' => true, 'is_featured' => true]);

    $html = $this->get('/produk')->getContent();
    preg_match('#id="pilihan-distrik".*?</section>#s', $html, $row);
    expect(substr_count($row[0], '<article'))->toBe(8)->and($row[0])->not->toContain('Produk Kelompok Tutup');

    foreach (['/produk?page=2', '/produk?q=kakao', "/produk?bidang={$this->kebun->id}"] as $url) {
        expect($this->get($url)->getContent())->not->toContain('id="judul-pilihan"');
    }
});

test('paginasi 16 per halaman mempertahankan pencarian, filter, dan urutan', function () {
    Product::factory()->for($this->beta)->count(18)->sequence(fn ($s) => ['name' => 'Ikan Seri '.str_pad($s->index, 2, '0', STR_PAD_LEFT), 'is_public' => true])->create();

    $html = $this->get("/produk?q=seri&bidang={$this->ikan->id}&urut=nama")->getContent();
    preg_match('#data-directory-pages.*?</nav>#s', $html, $pages);

    expect(catalogCards($html))->toHaveCount(16)
        ->and(urldecode(html_entity_decode($pages[0])))->toContain('q=seri')->toContain('bidang='.$this->ikan->id)->toContain('urut=nama')->toContain('page=2')
        ->and(catalogCards($this->get("/produk?q=seri&bidang={$this->ikan->id}&urut=nama&page=2")->getContent()))->toHaveCount(2);
});

test('kartu: label bidang, nama disorot, kelompok, kampung, kapasitas, pil Pilihan distrik, tautan ke detail', function () {
    $html = $this->get('/produk?q=kakao')->getContent();
    preg_match('#<ul data-cards.*?</ul>#s', $html, $grid);

    expect($grid[0])->toContain('<mark class="rounded-sm bg-highlight text-ink">Kakao</mark> kering')
        ->toContain('text-clay uppercase">Perikanan</p>') // bidang pertama menurut abjad
        ->toContain('Kelompok Contoh Alfa')->toContain('Kampung Contoh 1')->toContain('200 kg/bulan')
        ->toContain('bg-sun text-ink')->toContain('Pilihan distrik')->toContain('Lihat detail')
        ->toContain('href="'.route('public.product', $this->kakao->slug).'"')
        ->and(substr_count($grid[0], '<a '))->toBe(1);
    // Saat bidang disaring, label kartu memakai bidang itu.
    expect($this->get("/produk?bidang={$this->kebun->id}")->getContent())->toContain('text-clay uppercase">Perkebunan</p>');
});

test('judul, deskripsi, dan canonical dinamis; keadaan kosong menawarkan hapus filter', function () {
    $this->get("/produk?bidang={$this->kebun->id}&kampung={$this->satu->id}&q=x&urut=nama")
        ->assertSee('<title>Produk Perkebunan di Kampung Contoh 1 · ', false)
        ->assertSee('<link rel="canonical" href="'.e(route('public.products', ['bidang' => $this->kebun->id, 'kampung' => $this->satu->id])).'">', false)
        ->assertSee('Tidak ada produk yang cocok')->assertSee('Hapus semua filter');
});

test('detail produk menampilkan info, kartu kelompok, dan produk lain serta serupa', function () {
    $this->alfa->forceFill(['profile_status' => ProfileStatus::Verified, 'institution_type_id' => InstitutionType::factory()->create(['name' => 'KUB'])->id])->saveQuietly();
    $sibling = Product::factory()->for($this->alfa)->create(['name' => 'Kakao bubuk', 'is_public' => true]);

    $html = $this->get('/produk/kakao-kering')->assertOk()
        ->assertSee('<title>Kakao kering dari Kelompok Contoh Alfa · ', false)
        ->assertSeeInOrder(['Kapasitas', '200 kg/bulan', 'Satuan', 'kg', 'Kampung', 'Kampung Contoh 1', 'Jenis kelembagaan', 'KUB'])
        ->assertSee('Diverifikasi OPD')->assertSee('href="'.route('public.organization', $this->alfa->slug).'"', false)
        ->getContent();

    preg_match('#aria-labelledby="produk-lain".*?</section>#s', $html, $siblings);
    preg_match('#aria-labelledby="produk-serupa".*?</section>#s', $html, $similar);
    expect($siblings[0])->toContain('Kakao bubuk')->not->toContain('Produk Draf')
        ->and($similar[0])->toContain('Ikan asap')->toContain('Kopi bubuk')->not->toContain('Kakao bubuk')->not->toContain('Produk Kelompok');
});

test('detail produk 404 untuk produk privat, kelompok privat atau ditutup, dan slug tidak ada', function (string $slug) {
    $this->get('/produk/'.$slug)->assertNotFound();
})->with(['produk-draf', 'produk-kelompok-tutup', 'produk-kelompok-privat', 'tidak-ada']);

test('tombol Hubungi kelompok dan nomor telepon hanya tampil bila kontak kelompok boleh publik', function () {
    $this->alfa->forceFill(['phone' => '0812-3456-7890', 'show_contact_publicly' => false])->saveQuietly();

    $this->get('/produk/kakao-kering')->assertOk()
        ->assertDontSee('Hubungi kelompok')->assertDontSee('wa.me/62')->assertDontSee('3456')
        ->assertSee('Lihat profil kelompok');

    $this->alfa->forceFill(['show_contact_publicly' => true])->saveQuietly();
    $this->get('/produk/kakao-kering')
        ->assertSee('Hubungi kelompok')->assertSee('https://wa.me/6281234567890?text=', false)->assertSee('tel:081234567890', false);
});

test('Open Graph memakai foto produk dan JSON-LD Product tanpa harga atau offer', function () {
    $this->kakao->forceFill(['photo_path' => 'products/1/a.jpg', 'description' => 'Biji kakao fermentasi.'])->saveQuietly();

    $html = $this->get('/produk/kakao-kering')->assertSee('<meta property="og:image" content="'.$this->kakao->photoUrl().'">', false)->getContent();
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $script);
    $data = json_decode($script[1], true);

    expect($data)->toMatchArray(['@type' => 'Product', 'name' => 'Kakao kering', 'description' => 'Biji kakao fermentasi.', 'brand' => ['@type' => 'Brand', 'name' => 'Kelompok Contoh Alfa']])
        ->and($data['image'])->toBe([$this->kakao->photoUrl()])
        ->and($data)->not->toHaveKeys(['offers', 'price', 'priceCurrency'])
        ->and($script[1])->not->toContain('price')->not->toContain('offer');
});

test('slug produk dibuat otomatis, unik, dan tidak berubah saat produk diganti nama', function () {
    $twin = Product::factory()->for($this->beta)->create(['name' => 'Kakao kering', 'is_public' => true]);
    $third = Product::factory()->for($this->beta)->create(['name' => 'Kakao Kering']);

    expect($this->kakao->slug)->toBe('kakao-kering')->and($twin->slug)->toBe('kakao-kering-2')->and($third->slug)->toBe('kakao-kering-3');

    $this->kakao->update(['name' => 'Kakao premium']);
    expect($this->kakao->fresh()->slug)->toBe('kakao-kering');
    $this->get('/produk/kakao-kering-2')->assertOk()->assertSee('Kelompok Contoh Beta');
});

test('galeri: foto lampiran publik tampil dan bisa dibuka tamu; lampiran privat atau milik produk lain 404', function () {
    Storage::fake('local');
    $this->kakao->forceFill(['photo_path' => 'products/1/a.jpg'])->saveQuietly();
    $attach = fn (Product $product, string $visibility, string $mime = 'image/jpeg') => tap($product->attachments()->create([
        'disk' => 'local', 'path' => 'attachments/'.uniqid().'.jpg', 'original_name' => 'foto.jpg', 'mime_type' => $mime, 'size' => 10, 'visibility' => $visibility,
    ]), fn (Attachment $attachment) => Storage::disk('local')->put($attachment->path, imageBytes(10, 10)));

    $public = $attach($this->kakao, 'public');
    $private = $attach($this->kakao, 'private');
    $pdf = $attach($this->kakao, 'public', 'application/pdf');
    $other = $attach($this->ikanAsap, 'public');

    $html = $this->get('/produk/kakao-kering')->getContent();
    expect($html)->toContain(route('public.product-photo', [$this->kakao->id, $public->id]))
        ->not->toContain('/lampiran/'.$private->id)->not->toContain('lampiran/'.$pdf->id)
        ->toContain('Tampilkan foto 2 dari 2')->toContain('<dialog');

    $this->get(route('public.product-photo', [$this->kakao->id, $public->id]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('public.product-photo', [$this->kakao->id, $private->id]))->assertNotFound();
    $this->get(route('public.product-photo', [$this->kakao->id, $pdf->id]))->assertNotFound();
    $this->get(route('public.product-photo', [$this->kakao->id, $other->id]))->assertNotFound();

    // Produk berubah privat: foto lampirannya ikut tertutup.
    $this->kakao->update(['is_public' => false]);
    $this->get(route('public.product-photo', [$this->kakao->id, $public->id]))->assertNotFound();
});

test('foto produk kelompok yang tidak aktif sementara tetap tampil publik (sama dengan katalog)', function () {
    Storage::fake('local');
    Storage::disk('local')->put('products/9/a.jpg', imageBytes(10, 10));
    $this->kopi->forceFill(['photo_path' => 'products/9/a.jpg'])->saveQuietly();

    $this->get("/foto-produk/{$this->kopi->id}")->assertOk();
    $this->get('/produk/kopi-bubuk')->assertOk()->assertSee('Kelompok tidak aktif sementara');
});

test('kartu produk di Beranda dan profil kelompok menaut ke halaman detail produk', function () {
    $home = $this->get('/')->getContent();
    preg_match('#id="produk-unggulan".*?</section>#s', $home, $section);
    expect($section[0])->toContain('href="'.route('public.product', 'kakao-kering').'"')->not->toContain('#produk"');

    $this->get('/direktori/'.$this->alfa->slug)->assertSee('href="'.route('public.product', 'kakao-kering').'"', false)
        ->assertDontSee('produk-draf');
});

test('sitemap memuat halaman detail produk publik saja', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain(route('public.product', 'kakao-kering'))->toContain(route('public.product', 'kopi-bubuk'))
        ->not->toContain('produk-draf')->not->toContain('produk-kelompok-tutup')->not->toContain('produk-kelompok-privat');
});
