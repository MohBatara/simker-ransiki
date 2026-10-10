<?php

use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;

test('halaman masuk: panel forest polos tanpa foto, form di kanan, tombol tampilkan kata sandi', function () {
    $html = $this->get('/login')->assertOk()
        ->assertSee('Sistem informasi untuk mendata, melaporkan, dan memantau kelembagaan ekonomi di Distrik Ransiki.')
        ->assertSee('aria-label="Tampilkan kata sandi"', false)
        ->assertSee("x-bind:type=\"show ? 'text' : 'password'\"", false)
        ->assertSee('border-paper text-paper', false)
        ->getContent();

    expect($html)->toContain('bg-forest')->toContain('text-forest-soft')->not->toContain('<picture')->not->toContain('bg-forest/70');
});

test('halaman masuk dengan foto sampul: overlay padat 70% dan teks sekunder putih', function () {
    SiteSetting::put(['cover_photo_path' => 'site/sampul.jpg', 'cover_credit' => 'Fotografer Contoh']);

    $html = $this->get('/login')->assertOk()->assertSee('Foto: Fotografer Contoh')->getContent();

    expect($html)->toContain('<picture')->toContain('bg-forest/70')->not->toContain('text-forest-soft');
});

test('detail kelompok: badge status di samping judul dan fakta kunci di header', function () {
    $opd = Opd::factory()->create(['short_name' => 'DPC']);
    $organization = Organization::factory()->create(['status' => OrganizationStatus::Inactive]);
    $organization->opds()->attach($opd);
    Product::factory()->for($organization)->count(2)->create();
    $report = MonthlyReport::factory()->for($organization)->status(ReportStatus::Verified)->create(['period' => '2026-09-01', 'submitted_at' => now()]);

    $html = $this->actingAs(User::factory()->admin()->create())->get("/admin/kelompok/{$organization->id}")->assertOk()
        ->assertSee('OPD pembina')
        ->assertSee('DPC')
        ->assertSee('Laporan terakhir')
        ->assertSee("/admin/laporan/{$report->id}")
        ->assertSee('Terverifikasi')
        ->assertSee('2 produk')
        ->assertSee('Belum ada akun')
        ->assertSee('/admin/pengguna/tambah?kelompok='.$organization->id, false)
        ->assertSee('Buat akun')
        ->getContent();

    // Badge berada di baris judul, sebelum keterangan dan fakta kunci.
    expect($html)->toMatch('/<h1[^>]*>.*?<\/h1>\s*<span[^>]*>\s*Tidak aktif\s*<\/span>/s');
});

test('detail kelompok untuk OPD tidak menampilkan fakta akun', function () {
    $opd = Opd::factory()->create();
    $organization = Organization::factory()->create();
    $organization->opds()->attach($opd);

    $this->actingAs(User::factory()->opd($opd)->create())->get("/opd/kelompok/{$organization->id}")->assertOk()
        ->assertSee('Belum ada laporan')
        ->assertDontSee('Buat akun');
});

test('tab profil: kolom utama dan kolom samping yang tidak diregangkan', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->kelompok($organization)->create())->get('/kelompok/profil')->assertOk()
        ->assertSee('xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start', false)
        ->assertSeeInOrder(['Identitas', 'Informasi Usaha', 'Dokumen kelompok', 'Bidang Usaha &amp; OPD Terkait', 'Legalitas'], false);
});

test('form laporan: label ringkasan berubah bila tidak ada aktivitas, dan bilah progres bagian', function () {
    $organization = Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);
    $report = MonthlyReport::factory()->for($organization)->create(['period' => '2026-09-01']);

    $this->actingAs(User::factory()->kelompok($organization)->create())->get("/kelompok/laporan/{$report->id}/ubah")->assertOk()
        ->assertSee('x-text="data.no_activity ? &#039;Keterangan (opsional)&#039; : &#039;Ringkasan aktivitas&#039;"', false)
        ->assertSee('x-bind:style="`width: ${(index + 1) / sections.length * 100}%`"', false);
});

test('notifikasi kosong hanya menampilkan empty state', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->kelompok($organization)->create())->get('/notifikasi')->assertOk()
        ->assertSee('Belum ada notifikasi')
        ->assertDontSee('Semua sudah dibaca');
});
