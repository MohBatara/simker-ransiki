<?php

use App\Enums\ProfileStatus;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Support\Navigation;

beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->otherOpd = Opd::factory()->create();
    $this->ours = Organization::factory()->create();
    $this->ours->opds()->attach($this->opd);
    $this->foreign = Organization::factory()->create();
    $this->foreign->opds()->attach($this->otherOpd);
    $this->orphan = Organization::factory()->create(); // tanpa OPD pembina: hanya Admin yang bisa memverifikasi
    // Profil terverifikasi: antrean verifikasi profil hanya berisi yang diatur di tiap tes.
    Organization::query()->update(['profile_status' => ProfileStatus::Verified]);

    $this->admin = User::factory()->admin()->create();
    $this->opdUser = User::factory()->opd($this->opd)->create();
    $this->otherOpdUser = User::factory()->opd($this->otherOpd)->create();
});

test('URL lama Admin dan OPD dialihkan permanen ke tab baru, sisa path dan query ikut', function (string $from, string $to) {
    $this->actingAs($this->admin)->get($from)->assertStatus(301)->assertRedirect($to);
})->with([
    ['/admin/statistik', '/admin/dasbor/ekonomi'],
    ['/admin/statistik?dari=2026-01&sampai=2026-06', '/admin/dasbor/ekonomi?dari=2026-01&sampai=2026-06'],
    ['/opd/statistik/legalitas', '/opd/dasbor/legalitas'],
    ['/admin/statistik/legalitas/unduh?village=3', '/admin/dasbor/legalitas/unduh?village=3'],
    ['/admin/verifikasi-profil', '/admin/kelompok/verifikasi-profil'],
    ['/opd/verifikasi-profil', '/opd/kelompok/verifikasi-profil'],
    ['/admin/verifikasi', '/admin/laporan/verifikasi'],
    ['/opd/verifikasi?periode=2026-09', '/opd/laporan/verifikasi?periode=2026-09'],
    ['/opd/masalah', '/opd/laporan/masalah'],
    ['/admin/masalah/12', '/admin/laporan/masalah/12'],
    ['/opd/masalah?lama=30', '/opd/laporan/masalah?lama=30'],
    ['/admin/konten', '/admin/website'],
    ['/admin/konten/profil', '/admin/website/profil'],
    ['/admin/konten/halaman', '/admin/website/halaman'],
    ['/admin/berita', '/admin/website/berita'],
    ['/admin/berita/5/ubah', '/admin/website/berita/5/ubah'],
    ['/admin/pengguna', '/admin/pengaturan/pengguna'],
    ['/admin/pengguna/3/ubah', '/admin/pengaturan/pengguna/3/ubah'],
    ['/admin/data-master/kampung', '/admin/pengaturan/data-master/kampung'],
    ['/admin/pengaturan', '/admin/pengaturan/pengguna'],
]);

test('URL lama khusus Admin tidak dialihkan untuk area OPD', function () {
    $this->actingAs($this->opdUser)->get('/opd/konten')->assertNotFound();
    $this->actingAs($this->opdUser)->get('/opd/pengguna')->assertNotFound();
});

test('URL baru tetap dijaga peran: OPD tidak bisa membuka menu Admin, kelompok tidak bisa membuka area OPD', function () {
    $this->actingAs($this->opdUser)->get('/admin/website')->assertForbidden();
    $this->actingAs($this->opdUser)->get('/admin/pengaturan/pengguna')->assertForbidden();
    $this->actingAs(User::factory()->kelompok($this->ours)->create())->get('/opd/laporan/verifikasi')->assertForbidden();
});

test('badge menunggu verifikasi mengikuti cakupan data tiap peran', function () {
    MonthlyReport::factory()->for($this->ours)->status(ReportStatus::Submitted)->create(['period' => '2026-08-01']);
    MonthlyReport::factory()->for($this->ours)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01']);
    MonthlyReport::factory()->for($this->ours)->status(ReportStatus::Draft)->create(['period' => '2026-07-01']);
    MonthlyReport::factory()->for($this->foreign)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01']);
    MonthlyReport::factory()->for($this->orphan)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01']);
    $this->ours->forceFill(['profile_status' => ProfileStatus::NeedsReview])->save();
    $this->orphan->forceFill(['profile_status' => ProfileStatus::NeedsReview])->save();
    Organization::factory()->closed()->create(['profile_status' => ProfileStatus::NeedsReview]);

    $this->actingAs($this->opdUser);
    // profiles = badge menu Kelompok (hanya perubahan menunggu); profile_queue = isi tab (perubahan + belum pernah).
    expect(Navigation::counts($this->opdUser))->toBe(['reports' => 2, 'profiles' => 1, 'profiles_new' => 0, 'profile_queue' => 1]);
    // Admin: semua laporan diajukan di distrik (sama dengan isi tab Perlu diverifikasi).
    expect(Navigation::counts($this->admin))->toBe(['reports' => 4, 'profiles' => 2, 'profiles_new' => 0, 'profile_queue' => 2])
        ->and(Navigation::counts($this->otherOpdUser))->toBe(['reports' => 1, 'profiles' => 0, 'profiles_new' => 0, 'profile_queue' => 0]);

    $html = $this->get('/opd')->assertOk()->getContent();
    expect($html)->toContain('aria-label="Laporan, 2 menunggu verifikasi"')
        ->toContain('aria-label="Kelompok binaan, 1 menunggu verifikasi"')
        ->toMatch('#<span aria-hidden="true" class="[^"]*\bbg-sun\b[^"]*\btext-ink\b[^"]*">2</span>#');

    // Jumlah sama dengan isi tab antrean.
    expect($this->get('/opd/laporan/verifikasi')->viewData('reports')->total())->toBe(2)
        ->and($this->get('/opd/kelompok/verifikasi-profil')->viewData('organizations')->total())->toBe(1);
    $this->actingAs($this->admin);
    expect($this->get('/admin/laporan/verifikasi')->viewData('reports')->total())->toBe(4)
        ->and($this->get('/admin/laporan/verifikasi?hanya=admin')->viewData('reports')->total())->toBe(1);
});

test('badge tidak tampil bila tidak ada yang menunggu', function () {
    $html = $this->actingAs($this->otherOpdUser)->get('/opd')->assertOk()->getContent();

    expect($html)->toContain('aria-label="Laporan"')->toContain('aria-label="Kelompok binaan"')
        ->not->toContain('menunggu verifikasi"')->not->toContain('bg-sun');
});

test('setiap tab punya URL sendiri, tab aktif ditandai, dan remah mengikuti menu', function (string $url, string $section, string $tab, array $tabs) {
    $user = str_starts_with($url, '/admin') ? $this->admin : $this->opdUser;
    $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

    preg_match('#<nav x-ref="scroller"[^>]*aria-label="'.preg_quote($section, '#').'"\s*>(.*?)</nav>#s', $html, $nav);
    expect($nav)->not->toBeEmpty("Tab {$section} tidak ditemukan di {$url}")
        ->and(html_entity_decode(trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<span[^>]*rounded-pill[^>]*>.*?</span>#s', '', $nav[1]))))))->toBe(implode(' ', $tabs));
    preg_match('#<a[^>]*aria-current="page"[^>]*>(.*?)</a>#s', $nav[1], $current);
    expect(html_entity_decode(trim(strip_tags(preg_replace('#<span.*?</span>#s', '', $current[1])))))->toBe($tab)
        ->and(html_entity_decode(strip_tags($html)))->toContain($tab.' · '.$section);
})->with([
    'admin ekonomi' => ['/admin/dasbor/ekonomi', 'Dasbor', 'Ekonomi', ['Ringkasan', 'Ekonomi', 'Legalitas']],
    'admin kelompok' => ['/admin/kelompok', 'Kelompok', 'Semua kelompok', ['Semua kelompok', 'Verifikasi profil', 'Kemungkinan data ganda']],
    'admin data ganda' => ['/admin/kelompok/data-ganda', 'Kelompok', 'Kemungkinan data ganda', ['Semua kelompok', 'Verifikasi profil', 'Kemungkinan data ganda']],
    'admin antrean' => ['/admin/laporan/verifikasi', 'Laporan', 'Perlu diverifikasi', ['Semua laporan', 'Perlu diverifikasi', 'Masalah & kebutuhan']],
    'admin website' => ['/admin/website', 'Website', 'Konten Beranda', ['Konten Beranda', 'Profil Ransiki', 'Halaman & kontak', 'Berita & kegiatan']],
    'admin berita' => ['/admin/website/berita', 'Website', 'Berita & kegiatan', ['Konten Beranda', 'Profil Ransiki', 'Halaman & kontak', 'Berita & kegiatan']],
    'admin pengguna' => ['/admin/pengaturan/pengguna', 'Pengaturan', 'Pengguna', ['Pengguna', 'Data master']],
    'admin data master' => ['/admin/pengaturan/data-master/kampung', 'Pengaturan', 'Data master', ['Pengguna', 'Data master']],
    'opd legalitas' => ['/opd/dasbor/legalitas', 'Dasbor', 'Legalitas', ['Ringkasan', 'Ekonomi', 'Legalitas']],
    'opd kelompok' => ['/opd/kelompok/verifikasi-profil', 'Kelompok binaan', 'Verifikasi profil', ['Daftar', 'Verifikasi profil']],
    'opd masalah' => ['/opd/laporan/masalah', 'Laporan', 'Masalah & kebutuhan', ['Semua laporan', 'Perlu diverifikasi', 'Masalah & kebutuhan']],
]);

test('navigasi bawah HP: Admin dan Kelompok 4 menu + Lainnya, OPD 5 menu tanpa Lainnya', function () {
    $bottom = function (string $html) {
        preg_match_all('#<nav aria-label="Menu utama" x-data="\{ more: false \}".*?</nav>#s', $html, $nav);
        preg_match('#<ul class="grid h-16[^"]*">(.*?)</ul>#s', $nav[0][0], $list);

        return collect(explode('</li>', $list[1]))->map(fn ($item) => trim(preg_replace('/\s+/', ' ', strip_tags($item))))->filter()->values()->all();
    };

    expect($bottom($this->actingAs($this->admin)->get('/admin')->getContent()))->toBe(['Dasbor', 'Kelompok', 'Laporan', 'Website', 'Lainnya'])
        ->and($bottom($this->actingAs($this->opdUser)->get('/opd')->getContent()))->toBe(['Dasbor', 'Kelompok', 'Laporan', 'Notifikasi', 'Akun']);

    // Kelompok: 4 menu utama + Lainnya (Legalitas & Izin, Notifikasi, Akun Saya).
    $kelompok = $this->actingAs(User::factory()->kelompok($this->ours)->create())->get('/kelompok')->getContent();
    expect($bottom($kelompok))->toBe(['Dasbor', 'Profil', 'Produk', 'Laporan', 'Lainnya']);
    preg_match('#Menu lainnya.*?</ul>#s', $kelompok, $kelompokMore);
    expect(html_entity_decode(preg_replace('/\s+/', ' ', strip_tags($kelompokMore[0]))))->toContain('Legalitas & Izin')->toContain('Notifikasi')->toContain('Akun Saya');

    $sheet = $this->actingAs($this->admin)->get('/admin')->getContent();
    preg_match('#Menu lainnya.*?</ul>#s', $sheet, $more);
    expect(preg_replace('/\s+/', ' ', strip_tags($more[0])))->toContain('Pengaturan')->toContain('Notifikasi')->toContain('Akun Saya');
});
