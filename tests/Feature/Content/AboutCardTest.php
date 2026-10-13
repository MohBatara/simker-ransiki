<?php

use App\Models\BusinessSector;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ImageVariants;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
});

function aboutSection(string $html): string
{
    preg_match('#aria-labelledby="tentang".*?</section>#s', $html, $match);

    return $match[0];
}

test('tiga kartu bawaan: judul, kalimat, tautan bidang tujuan, foto bidang bila kartu tanpa foto', function () {
    $kebun = BusinessSector::factory()->create(['name' => 'Perkebunan', 'photo_path' => 'site/bidang-kebun.jpg']);
    $ikan = BusinessSector::factory()->create(['name' => 'Perikanan']);

    $section = aboutSection($this->get('/')->assertOk()->getContent());
    preg_match_all('#<li class="relative[^"]*">\s*<a href="([^"]+)"#', $section, $links);

    expect($section)->toContain('Kebun kakao dan kopi')->toContain('Laut dan perikanan')->toContain('Olahan rumahan')
        ->toContain('Kelompok pekebun merawat kakao, kopi, dan pinang di sekitar kampung.')
        ->toContain("/foto-bidang/{$kebun->id}")
        ->toContain('card-bite')->toContain('Baca profil lengkap')->toContain('x-data="scrollDots"')->toContain('Kartu 3 dari 3')
        ->and($links[1])->toBe([
            route('public.directory', ['bidang' => $kebun->id]),
            route('public.directory', ['bidang' => $ikan->id]),
            route('public.directory'), // "Pengolahan Hasil" belum ada di data master tes
        ])
        // Susunan lama (foto 4:5 kiri + teks kanan) sudah tidak ada.
        ->and($section)->not->toContain('md:aspect-[4/5]')->not->toContain('<picture class="block overflow-hidden rounded-photo');
});

test('admin mengubah kartu: judul, kalimat, bidang tujuan, dan foto (WebP), lalu menghapus fotonya', function () {
    $dagang = BusinessSector::factory()->create(['name' => 'Perdagangan']);
    $this->actingAs($this->admin)->get('/admin/konten')->assertOk()->assertSee('Kartu Mengenal Distrik Ransiki')->assertSee('Simpan kartu 3');

    $this->post('/admin/konten/kartu/2', ['kartu_2' => [
        'title' => 'Pasar kampung', 'text' => 'Kalimat contoh kartu dua.', 'sector' => $dagang->id,
        'photo' => realUpload('kartu.jpg', jpegWithExif(1000, 1250, orientation: 1)),
    ]])->assertRedirect(route('admin.content.edit'))->assertSessionHas('status', 'Kartu 2 disimpan.');

    $path = SiteSetting::get('about_card_2_photo_path');
    foreach (ImageVariants::HERO as $width) {
        expect(Storage::disk('local')->exists(ImageVariants::path($path, $width)))->toBeTrue();
    }

    $section = aboutSection($this->get('/')->getContent());
    expect($section)->toContain('Pasar kampung')->toContain('Kalimat contoh kartu dua.')
        ->toContain('href="'.route('public.directory', ['bidang' => $dagang->id]).'"')->toContain('/foto-kartu/2?ukuran=640');

    $this->post('/logout');
    $this->get('/foto-kartu/2?ukuran=640&format=webp')->assertOk()->assertHeader('Content-Type', 'image/webp');

    $this->actingAs($this->admin)->delete('/admin/konten/kartu/2/foto')->assertSessionHas('status', 'Foto kartu 2 dihapus.');
    expect(SiteSetting::get('about_card_2_photo_path'))->toBeNull()->and(Storage::disk('local')->allFiles('site'))->toBe([]);
});

test('validasi kartu: kalimat maks 90 karakter, foto minimal 800 px, error hanya di form kartu itu', function () {
    $this->actingAs($this->admin)->from('/admin/konten');

    $this->post('/admin/konten/kartu/1', ['kartu_1' => ['text' => str_repeat('a', 91)]])
        ->assertSessionHasErrors(['kartu_1.text' => 'Kalimat paling banyak 90 karakter.']);

    $this->post('/admin/konten/kartu/1', ['kartu_1' => ['photo' => realUpload('kecil.jpg', imageBytes(700, 900))]])
        ->assertSessionHasErrors(['kartu_1.photo' => 'Foto terlalu kecil: lebarnya 700 px, minimal 800 px. Pakai foto asli dari kamera, bukan hasil kiriman WhatsApp.']);

    expect(SiteSetting::get('about_card_1_photo_path'))->toBeNull();
});

test('ringkasan error tampil hanya di form kartu yang gagal', function () {
    $this->actingAs($this->admin)->from('/admin/konten')
        ->post('/admin/konten/kartu/1', ['kartu_1' => ['photo' => realUpload('kecil.jpg', imageBytes(700, 900))]]);

    $html = $this->get('/admin/konten')->assertSee('Kartu belum disimpan')->getContent();
    expect(substr_count($html, 'Kartu belum disimpan'))->toBe(1);
});

test('hanya Admin yang bisa mengubah kartu', function (string $role) {
    $user = $role === 'opd' ? User::factory()->opd()->create() : User::factory()->kelompok(Organization::factory()->create())->create();

    $this->actingAs($user)->post('/admin/konten/kartu/1', ['kartu_1' => ['title' => 'Diubah']])->assertForbidden();
    $this->delete('/admin/konten/kartu/1/foto')->assertForbidden();
    $this->post('/logout');
    $this->post('/admin/konten/kartu/1', ['kartu_1' => ['title' => 'Diubah']])->assertRedirect(route('login'));

    expect(SiteSetting::get('about_card_1_title'))->toBeNull();
})->with(['opd', 'kelompok']);

test('baris angka: angka publik dipisah titik tengah dan tanggal data', function () {
    $this->travelTo('2026-10-09 10:00:00');
    Organization::factory()->public()->create();

    $section = aboutSection($this->get('/')->getContent());

    expect($section)->toContain('>·</span>')->toContain('Data per 9 Oktober 2026')
        ->and(strip_tags($section))->toMatch('/1\s+Kelompok/');
});

test('kepala bagian: rata atas, judul 5 kolom, teks mulai kolom 7, kata pertama judul dipisah di desktop', function () {
    $section = aboutSection($this->get('/')->getContent());

    expect($section)->toContain('md:grid-cols-12 md:items-start')->toContain('md:col-span-6 md:col-start-7')
        ->toMatch('#Mengenal<br class="hidden lg:block"> Distrik Ransiki#')
        ->toContain('leading-[1.6]')->not->toContain('lg:items-end');
});

