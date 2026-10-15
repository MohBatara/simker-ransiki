<?php

use App\Enums\OrganizationStatus;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Village;

function facetCount(string $html, string $key): ?int
{
    return preg_match('#data-facet="'.$key.'"><span class="sr-only">, </span>([\d.]+)<#', $html, $match) ? (int) str_replace('.', '', $match[1]) : null;
}

function directoryCards(string $html): array
{
    preg_match_all('#<a href="[^"]*/direktori/[^"]+" title="([^"]+)"#', $html, $matches);

    return $matches[1];
}

beforeEach(function () {
    $this->kub = InstitutionType::factory()->create(['name' => 'KUB']);
    $this->bumdes = InstitutionType::factory()->create(['name' => 'BUMDes']);
    $this->kebun = BusinessSector::factory()->create(['name' => 'Perkebunan']);
    $this->ikan = BusinessSector::factory()->create(['name' => 'Perikanan']);
    $this->satu = Village::factory()->create(['name' => 'Kampung Contoh 1']);
    $this->dua = Village::factory()->create(['name' => 'Kampung Contoh 2']);

    $make = function (string $name, $type, $village, array $sectors, array $extra = []) {
        $organization = Organization::factory()->public()->create(['name' => $name, 'institution_type_id' => $type->id, 'village_id' => $village->id, ...$extra]);
        $organization->sectors()->attach(collect($sectors)->pluck('id'));

        return $organization;
    };

    // Alfa berbidang ganda: tidak boleh terhitung ganda di facet jenis/kampung.
    $this->alfa = $make('Kelompok Contoh Alfa', $this->kub, $this->satu, [$this->kebun, $this->ikan]);
    $this->beta = $make('Kelompok Contoh Beta', $this->kub, $this->dua, [$this->ikan]);
    $this->gama = $make('Kelompok Contoh Gama', $this->bumdes, $this->satu, [$this->kebun]);
    $make('Kelompok Contoh Tutup', $this->kub, $this->satu, [$this->kebun], ['status' => OrganizationStatus::Closed]);
    $make('Kelompok Contoh Privat', $this->kub, $this->satu, [$this->kebun], ['is_public' => false]);
    Product::factory()->for($this->beta)->create(['name' => 'Kopi bubuk', 'is_public' => true]);
    Product::factory()->for($this->gama)->create(['name' => 'Kopi luwak', 'is_public' => false]);
});

test('hanya kelompok publik yang tampil, dengan jumlah total publik di banner', function () {
    $html = $this->get('/direktori')->assertOk()->getContent();

    expect(directoryCards($html))->toBe(['Kelompok Contoh Alfa', 'Kelompok Contoh Beta', 'Kelompok Contoh Gama'])
        ->and($html)->not->toContain('Kelompok Contoh Tutup')->not->toContain('Kelompok Contoh Privat')
        ->and(strip_tags($html))->toMatch('/3\s+kelompok terdaftar/')->toMatch('/3\s+kelompok ditemukan/');
});

test('filter: pilihan dalam satu dimensi digabung ATAU, antardimensi DAN; nilai tunggal lama tetap diterima', function (string $query, array $expected) {
    $query = strtr($query, ['{kub}' => $this->kub->id, '{bumdes}' => $this->bumdes->id, '{kebun}' => $this->kebun->id, '{ikan}' => $this->ikan->id, '{satu}' => $this->satu->id, '{dua}' => $this->dua->id]);

    expect(directoryCards($this->get('/direktori?'.$query)->assertOk()->getContent()))->toBe($expected);
})->with([
    'bidang lama (tunggal)' => ['bidang={kebun}', ['Kelompok Contoh Alfa', 'Kelompok Contoh Gama']],
    'dua jenis' => ['jenis[]={kub}&jenis[]={bumdes}', ['Kelompok Contoh Alfa', 'Kelompok Contoh Beta', 'Kelompok Contoh Gama']],
    'jenis dan kampung' => ['jenis[]={kub}&kampung[]={satu}', ['Kelompok Contoh Alfa']],
    'dua bidang dan kampung' => ['bidang[]={kebun}&bidang[]={ikan}&kampung[]={dua}', ['Kelompok Contoh Beta']],
    'cari nama + filter' => ['q=gama&kampung[]={satu}', ['Kelompok Contoh Gama']],
    'cari produk publik' => ['q=kopi', ['Kelompok Contoh Beta']],
    'cari kampung' => ['q=contoh 2', ['Kelompok Contoh Beta']],
    'cari + filter tanpa hasil' => ['q=alfa&jenis[]={bumdes}', []],
    'nilai tidak sah diabaikan' => ['jenis[]=abc&bidang[x][]=1&urut=acak', ['Kelompok Contoh Alfa', 'Kelompok Contoh Beta', 'Kelompok Contoh Gama']],
]);

test('jumlah facet unik, hanya kelompok publik, dan mengikuti filter dimensi lain', function () {
    $html = $this->get('/direktori')->getContent();
    expect(facetCount($html, "jenis-{$this->kub->id}"))->toBe(2) // Alfa (dua bidang) dihitung sekali; Tutup/Privat tidak
        ->and(facetCount($html, "jenis-{$this->bumdes->id}"))->toBe(1)
        ->and(facetCount($html, "bidang-{$this->kebun->id}"))->toBe(2)
        ->and(facetCount($html, "bidang-{$this->ikan->id}"))->toBe(2)
        ->and(facetCount($html, "kampung-{$this->satu->id}"))->toBe(2);

    // Memilih bidang Perkebunan: jenis dan kampung ikut menyempit, opsi bidang lain tetap terhitung tanpa filter bidang.
    $html = $this->get("/direktori?bidang[]={$this->kebun->id}")->getContent();
    expect(facetCount($html, "jenis-{$this->kub->id}"))->toBe(1)
        ->and(facetCount($html, "kampung-{$this->dua->id}"))->toBe(0)
        ->and(facetCount($html, "bidang-{$this->ikan->id}"))->toBe(2)
        ->and($html)->toMatch('#name="bidang\[\]"\s+value="'.$this->kebun->id.'"\s+checked#')
        ->not->toMatch('#name="bidang\[\]"\s+value="'.$this->ikan->id.'"\s+checked#');

    // Pencarian ikut memengaruhi semua facet.
    expect(facetCount($this->get('/direktori?q=beta')->getContent(), "bidang-{$this->kebun->id}"))->toBe(0);
});

test('chip filter aktif menghapus satu filter dan mempertahankan pencarian', function () {
    $html = $this->get("/direktori?q=contoh&jenis[]={$this->kub->id}&bidang[]={$this->ikan->id}")->getContent();
    preg_match('#id="filter-aktif".*?</div>#s', $html, $chips);

    expect(strip_tags($chips[0]))->toContain('KUB')->toContain('Perikanan')
        ->and($chips[0])->toContain('href="'.e(route('public.directory', ['q' => 'contoh', 'jenis' => [], 'bidang' => [$this->ikan->id]])).'"');
});

test('paginasi 12 per halaman mempertahankan pencarian, filter, dan urutan', function () {
    Organization::factory()->public()->count(14)->sequence(fn ($s) => ['name' => 'Kelompok Contoh Seri '.str_pad($s->index, 2, '0', STR_PAD_LEFT), 'village_id' => $this->satu->id])
        ->create()->each(fn ($organization) => $organization->sectors()->attach($this->kebun));

    $html = $this->get("/direktori?q=seri&kampung[]={$this->satu->id}&urut=terbaru")->getContent();
    preg_match('#data-directory-pages.*?</nav>#s', $html, $pages);

    expect(directoryCards($html))->toHaveCount(12)
        ->and(urldecode(html_entity_decode($pages[0])))->toContain('q=seri')->toContain('kampung[0]='.$this->satu->id)->toContain('urut=terbaru')->toContain('page=2');

    expect(directoryCards($this->get("/direktori?q=seri&kampung[]={$this->satu->id}&page=2")->getContent()))->toHaveCount(2);
});

test('urutan Produk terbanyak dan Terbaru', function () {
    Product::factory()->for($this->gama)->count(2)->create(['is_public' => true]);

    expect(directoryCards($this->get('/direktori?urut=produk')->getContent()))->toBe(['Kelompok Contoh Gama', 'Kelompok Contoh Beta', 'Kelompok Contoh Alfa']);

    $this->alfa->forceFill(['created_at' => now()->addDay()])->saveQuietly();
    expect(directoryCards($this->get('/direktori?urut=terbaru')->getContent())[0])->toBe('Kelompok Contoh Alfa');
});

test('kartu: kata kunci disorot, jenis, kampung, bidang maks 2 lalu +n, jumlah produk publik, lencana verifikasi', function () {
    $sector = BusinessSector::factory()->create(['name' => 'UMKM']);
    $this->alfa->sectors()->attach($sector);
    $this->alfa->forceFill(['profile_status' => App\Enums\ProfileStatus::Verified])->saveQuietly();

    $html = $this->get('/direktori?q=alfa')->getContent();

    expect($html)->toContain('Kelompok Contoh <mark class="rounded-sm bg-highlight text-ink">Alfa</mark>')
        ->toContain('Diverifikasi OPD')->toContain('+1')
        ->and(strip_tags($html))->toMatch('/Kampung Contoh 1\s+Perikanan\s+Perkebunan/')->toMatch('/0\s+produk/');
    // Kata kunci di-escape sebelum disorot.
    $this->get('/direktori?q=<b>x</b>')->assertOk()->assertDontSee('<b>x</b>', false);
});

test('judul, deskripsi, dan canonical dinamis untuk halaman berfilter', function () {
    $this->get("/direktori?bidang[]={$this->kebun->id}&urut=produk&q=x")
        ->assertSee('<title>Kelompok Perkebunan di Distrik Ransiki · ', false)
        ->assertSee('<link rel="canonical" href="'.e(route('public.directory', ['bidang' => [$this->kebun->id]])).'">', false);

    $this->get("/direktori?jenis[]={$this->kub->id}&kampung[]={$this->satu->id}&kampung[]={$this->dua->id}")
        ->assertSee('<title>KUB di Kampung Contoh 1 dan Kampung Contoh 2 · ', false);
    $this->get('/direktori')->assertSee('<title>Direktori kelompok · ', false)
        ->assertSee('<link rel="canonical" href="'.route('public.directory').'">', false);
});

test('keadaan kosong menawarkan saran dan tombol hapus semua filter', function () {
    $this->get("/direktori?q=tidakada&jenis[]={$this->bumdes->id}")
        ->assertSee('Tidak ada kelompok yang cocok')->assertSee('Periksa ejaan')->assertSee('Kurangi jumlah filter')
        ->assertSee('Hapus semua filter');
});

test('data privat tidak tampil di direktori', function () {
    $this->alfa->forceFill([
        'leader_name' => 'Ketua Rahasia', 'phone' => '0812-9999-0000', 'email' => 'rahasia@contoh.test', 'address' => 'Alamat rahasia',
        'members_count' => 987, 'assets' => 'Aset rahasia', 'show_contact_publicly' => true,
    ])->saveQuietly();

    $this->get('/direktori')->assertOk()
        ->assertDontSee('Ketua Rahasia')->assertDontSee('0812-9999-0000')->assertDontSee('rahasia@contoh.test')
        ->assertDontSee('Alamat rahasia')->assertDontSee('987')->assertDontSee('Aset rahasia');
});
