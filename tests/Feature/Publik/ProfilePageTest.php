<?php

use App\Enums\OrganizationStatus;
use App\Models\BusinessSector;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Village;
use App\Support\ImageVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function profileSection(string $html, string $id): string
{
    preg_match('#<section id="'.$id.'".*?</section>#s', $html, $match);

    return $match[0] ?? '';
}

test('halaman memuat banner, navigasi dalam halaman, dan empat bagian berurutan', function () {
    $html = $this->get('/profil')->assertOk()->getContent();

    expect($html)->toContain('aria-label="Bagian halaman"')->toContain('x-data="sectionNav"')
        ->toContain('overlay-band-bottom')->toContain('/foto-hero/bawaan')
        ->and(strip_tags($html))->toMatch('/Beranda\s+Profil Ransiki\s+Profil Distrik Ransiki\s+Gambaran wilayah, potensi ekonomi, dan kampung di Distrik Ransiki\./');

    $positions = collect(['#sekilas', '#potensi', '#kampung', '#kantor'])->map(fn ($anchor) => strpos($html, 'href="'.$anchor.'"'));
    expect($positions->all())->each->toBeInt()
        ->and($positions->sort()->values()->all())->toBe($positions->all());

    $sections = collect(['sekilas', 'potensi', 'kampung', 'kantor'])->map(fn ($id) => strpos($html, '<section id="'.$id.'"'));
    expect($sections->sort()->values()->all())->toBe($sections->all())->and($sections->all())->each->toBeInt();
});

test('data singkat hanya menampilkan baris yang terisi; jumlah kampung dari Data Master', function () {
    Village::factory()->count(3)->create();

    $html = $this->get('/profil')->getContent();
    expect($html)->toContain('Data singkat')->toContain('Jumlah kampung')
        ->not->toContain('Luas wilayah')->not->toContain('Jumlah penduduk')->not->toContain('Kabupaten')
        ->not->toContain('Sumber data');

    SiteSetting::put(['profile_regency' => 'Kabupaten Contoh', 'profile_population' => '1.234 jiwa', 'profile_data_source' => 'Dokumen contoh 2025']);

    $card = $this->get('/profil')->getContent();
    expect(strip_tags($card))->toMatch('/Kabupaten\s+Kabupaten Contoh\s+Jumlah penduduk\s+1\.234 jiwa\s+Jumlah kampung\s+3\s+Sumber data: Dokumen contoh 2025/')
        ->and($card)->not->toContain('Luas wilayah')->not->toContain('Provinsi')->not->toContain('Ibu kota distrik');
});

test('tanpa data singkat dan tanpa kampung, kartu Data singkat tidak tampil', function () {
    $this->get('/profil')->assertOk()->assertDontSee('Data singkat')->assertSee('Daftar kampung sedang disiapkan.');
});

test('paragraf pembuka dan isi profil tampil; kosong memakai pesan sedang disiapkan', function () {
    $this->get('/profil')->assertSee('Profil distrik sedang disiapkan.');

    SiteSetting::put(['profile_intro' => 'Pembuka contoh profil.', 'profile_body' => "Isi **contoh**.\n\n<script>alert(1)</script>"]);

    $section = profileSection($this->get('/profil')->getContent(), 'sekilas');
    expect($section)->toContain('Pembuka contoh profil.')->toContain('<strong>contoh</strong>')
        ->not->toContain('<script>')->not->toContain('sedang disiapkan');
});

test('kampung: semua kampung urut abjad, hitungan hanya kelompok publik, tautan ke direktori tersaring', function () {
    $beta = Village::factory()->create(['name' => 'Kampung Contoh Beta']);
    $alfa = Village::factory()->create(['name' => 'Kampung Contoh Alfa']);
    $kosong = Village::factory()->create(['name' => 'Kampung Contoh Gama']);

    Organization::factory()->public()->count(2)->create(['village_id' => $alfa->id]);
    Organization::factory()->public()->create(['village_id' => $alfa->id, 'status' => OrganizationStatus::Inactive]);
    Organization::factory()->public()->create(['village_id' => $alfa->id, 'status' => OrganizationStatus::Closed]);
    Organization::factory()->create(['village_id' => $alfa->id, 'is_public' => false]);
    Organization::factory()->public()->create(['village_id' => $beta->id]);
    Organization::factory()->create(['village_id' => $kosong->id, 'is_public' => false]);

    $section = profileSection($this->get('/profil')->getContent(), 'kampung');

    expect(strip_tags($section))->toMatch('/Kampung Contoh Alfa\s+3 kelompok.*Kampung Contoh Beta\s+1 kelompok.*Kampung Contoh Gama\s+Belum ada kelompok terdaftar/s')
        ->and($section)->toContain('href="'.route('public.directory', ['kampung' => $alfa->id]).'"')
        ->toContain('href="'.route('public.directory', ['kampung' => $beta->id]).'"')
        ->not->toContain('kampung='.$kosong->id);

    // Tautan benar-benar menyaring direktori.
    $this->get(route('public.directory', ['kampung' => $beta->id]))->assertSee(Organization::where('village_id', $beta->id)->value('name'));
});

test('potensi ekonomi: bawaan tiga blok, hitungan hanya kelompok publik, tautan ke direktori per bidang', function () {
    $kebun = BusinessSector::factory()->create(['name' => 'Perkebunan']);
    $ikan = BusinessSector::factory()->create(['name' => 'Perikanan']);
    BusinessSector::factory()->create(['name' => 'Pengolahan Hasil']);

    Organization::factory()->public()->count(2)->create()->each(fn ($organization) => $organization->sectors()->attach($kebun));
    Organization::factory()->create(['is_public' => false])->sectors()->attach($kebun);
    Organization::factory()->public()->create(['status' => OrganizationStatus::Closed])->sectors()->attach($ikan);

    $section = profileSection($this->get('/profil')->getContent(), 'potensi');

    expect(strip_tags($section))->toMatch('/Perkebunan\s+Kebun kakao dan kopi.*2\s+kelompok\s+Lihat kelompok/s')
        ->toMatch('/Perikanan\s+Laut dan perikanan.*Belum ada kelompok terdaftar/s')
        ->toContain('Olahan rumahan')
        ->and($section)->toContain('href="'.route('public.directory', ['bidang' => $kebun->id]).'"')
        ->not->toContain('bidang='.$ikan->id)
        ->toContain('fade-right')->toContain('fade-left')->toContain('aspect-[5/4]')->toContain('md:-ml-8')->toContain('md:-mr-8');

    $this->get(route('public.directory', ['bidang' => $kebun->id]))->assertOk();
});

test('admin mengatur blok potensi (judul, teks, bidang, foto) dan kalimat pengantar', function () {
    Storage::fake('local');
    $sector = BusinessSector::factory()->create(['name' => 'UMKM']);
    Organization::factory()->public()->create()->sectors()->attach($sector);
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/konten/profil')->assertOk()->assertSee('Profil Ransiki')->assertSee('Blok 2')->assertSee('Data singkat');

    $this->post('/admin/konten/potensi/2', ['potensi_2' => [
        'title' => 'Judul blok contoh',
        'text' => 'Teks blok contoh.',
        'sector' => $sector->id,
        'photo' => UploadedFile::fake()->image('blok.jpg', 1200, 900),
    ]])->assertRedirect('/admin/konten/profil')->assertSessionHas('status', 'Blok potensi 2 disimpan.');
    $this->put('/admin/konten', ['potential_intro' => 'Pengantar potensi contoh.'])->assertSessionHasNoErrors();

    $path = SiteSetting::get('potential_2_photo_path');
    expect($path)->not->toBeNull()->and(Storage::disk('local')->exists($path))->toBeTrue();

    $section = profileSection($this->get('/profil')->getContent(), 'potensi');
    expect($section)->toContain('Judul blok contoh')->toContain('Teks blok contoh.')->toContain('Pengantar potensi contoh.')
        ->toContain('/foto-potensi/2?')->toContain('bidang='.$sector->id);
    $this->get('/foto-potensi/2')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->delete('/admin/konten/potensi/2/foto')->assertRedirect('/admin/konten/profil');
    expect(SiteSetting::get('potential_2_photo_path'))->toBeNull()->and(Storage::disk('local')->exists($path))->toBeFalse();
});

test('banner dan Open Graph memakai foto banner; tanpa itu foto sampul, lalu foto bawaan', function () {
    Storage::fake('local');
    $this->get('/profil')->assertSee('og:image" content="'.route('public.hero-default', ['v' => substr(md5('bawaan-1'), 0, 8)]), false);

    SiteSetting::put(['cover_photo_path' => 'site/cover.jpg']);
    $this->get('/profil')->assertSee('/foto-sampul?v=', false)->assertDontSee('/foto-profil');

    $this->actingAs(User::factory()->admin()->create())
        ->post('/admin/konten/foto-profil', ['file' => UploadedFile::fake()->image('banner.jpg', 1800, 900)])
        ->assertCreated();
    $path = SiteSetting::get('profile_photo_path');
    expect(Storage::disk('local')->exists(ImageVariants::path($path, 1600)))->toBeTrue();

    $html = $this->get('/profil')->getContent();
    expect($html)->toContain('og:image" content="'.route('public.site-photo', ['slot' => 'profil', 'v' => substr(md5($path), 0, 8)]).'"')
        ->toContain('fetchpriority="high"');
    preg_match('#<section class="on-dark.*?</section>#s', $html, $banner);
    expect($banner[0])->toContain('/foto-profil?')->not->toContain('/foto-sampul');
    $this->get('/foto-profil')->assertOk();
});

test('kantor distrik: hanya baris yang terisi, tombol Hubungi kami, telepon tel:, foto kantor atau gambar ikon', function () {
    Storage::fake('local');
    $html = profileSection($this->get('/profil')->getContent(), 'kantor');
    expect($html)->toContain('Kantor Distrik Ransiki')->toContain('Informasi kantor sedang disiapkan.')
        ->toContain('Hubungi kami')->toContain('href="'.route('public.contact').'"')
        ->not->toContain('tel:')->not->toContain('Jam layanan');

    SiteSetting::put(['office_address' => 'Alamat contoh', 'office_phone' => '0800-1234-5678', 'office_hours' => 'Senin sampai Jumat']);
    $html = profileSection($this->get('/profil')->getContent(), 'kantor');
    expect(strip_tags($html))->toMatch('/Alamat\s+Alamat contoh\s+Jam layanan\s+Senin sampai Jumat\s+Telepon\s+0800-1234-5678/')
        ->and($html)->toContain('href="tel:080012345678"')->not->toContain('Email')->toContain('data-office-illustration')->not->toContain('/foto-kantor');

    SiteSetting::put(['office_photo_path' => 'site/kantor.jpg']);
    expect(profileSection($this->get('/profil')->getContent(), 'kantor'))->toContain('/foto-kantor?v=')->not->toContain('data-office-illustration');
});

test('hanya Admin yang bisa mengubah konten Profil Ransiki', function (string $role) {
    Storage::fake('local');
    $user = $role === 'opd' ? User::factory()->opd()->create() : User::factory()->kelompok(Organization::factory()->create())->create();
    $this->actingAs($user);

    $this->get('/admin/konten/profil')->assertForbidden();
    $this->put('/admin/konten', ['profile_intro' => 'Diubah tanpa hak.', 'profile_area' => '1 km²'])->assertForbidden();
    $this->post('/admin/konten/potensi/1', ['potensi_1' => ['title' => 'Diubah tanpa hak.']])->assertForbidden();
    $this->delete('/admin/konten/potensi/1/foto')->assertForbidden();
    $this->post('/admin/konten/foto-profil', ['file' => UploadedFile::fake()->image('a.jpg', 1600, 900)])->assertForbidden();
    $this->post('/admin/konten/foto-kantor', ['file' => UploadedFile::fake()->image('a.jpg', 1600, 900)])->assertForbidden();

    expect(SiteSetting::get('profile_intro'))->toBeNull()->and(SiteSetting::get('potential_1_title'))->toBeNull()
        ->and(SiteSetting::get('profile_photo_path'))->toBeNull();
})->with(['opd', 'kelompok']);

test('tamu diarahkan ke halaman masuk saat membuka editor Profil Ransiki', function () {
    $this->get('/admin/konten/profil')->assertRedirect('/login');
    $this->post('/admin/konten/potensi/1')->assertRedirect('/login');
});

test('validasi data singkat dan blok potensi berbahasa Indonesia', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->from('/admin/konten/profil')->put('/admin/konten', ['profile_area' => str_repeat('a', 81)])
        ->assertSessionHasErrors(['profile_area' => 'Luas wilayah paling banyak 80 karakter.']);
    $this->from('/admin/konten/profil')->post('/admin/konten/potensi/3', ['potensi_3' => ['text' => str_repeat('a', 401)]])
        ->assertSessionHasErrors(['potensi_3.text' => 'Teks paling banyak 400 karakter.']);
});

test('tampilan modern: banner zoom, nav pil berlatar blur, data singkat bento dengan ubin kampung forest, angka berubin', function () {
    Village::factory()->count(2)->create();
    SiteSetting::put(['profile_regency' => 'Kabupaten Contoh']);
    $html = $this->get('/profil')->getContent();

    preg_match('#<nav aria-label="Bagian halaman".*?</nav>#s', $html, $nav);
    preg_match('#<aside[^>]*aria-labelledby="data-singkat".*?</aside>#s', $html, $bento);

    expect($html)->toContain('banner-zoom')->toContain('lg:text-hero-lg')->toContain('lg:min-h-[min(70vh,40rem)]')
        ->and($nav[0])->toContain('backdrop-blur-[12px]')->toContain('rounded-full')->toContain('bg-brand text-paper')
        ->and($bento[0])->toContain('grid grid-cols-2')->toContain('rounded-tile p-4')->toContain('bg-cream')
        ->and(strip_tags($bento[0]))->toMatch('/Kabupaten\s+Kabupaten Contoh\s+Jumlah kampung\s+2/')
        ->and($bento[0])->toMatch('#bg-forest text-paper[^>]*>\s*<dt[^>]*text-forest-soft[^>]*>Jumlah kampung#')
        ->and(profileSection($html, 'sekilas'))->toContain('rounded-[20px] border border-line bg-paper');
});
