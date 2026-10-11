<?php

use App\Enums\IssueKind;
use App\Enums\LegalStatus;
use App\Enums\LicenseStatus;
use App\Models\Attachment;
use App\Models\BusinessSector;
use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\Product;

beforeEach(function () {
    $this->sector = BusinessSector::factory()->create(['name' => 'Perkebunan']);
    $this->listed = Organization::factory()->public()->create([
        'name' => 'KUB Contoh Terbuka',
        'leader_name' => 'KETUA-RAHASIA', 'phone' => '0800-1111-2222', 'email' => 'rahasia@contoh.test', 'address' => 'ALAMAT-RAHASIA',
        'assets' => 'ASET-RAHASIA', 'facilities' => 'FASILITAS-RAHASIA', 'members_count' => 987654,
        'legal_status' => LegalStatus::InProcess, 'legal_notes' => 'CATATAN-RAHASIA',
    ]);
    $this->listed->sectors()->attach($this->sector);
    Product::factory()->for($this->listed)->create(['name' => 'Kakao kering', 'is_public' => true]);
    Product::factory()->for($this->listed)->create(['name' => 'Produk Tersembunyi', 'is_public' => false]);
    License::factory()->for($this->listed)->create(['type' => 'PIRT', 'status' => LicenseStatus::Issued, 'number' => 'NOMOR-IZIN-RAHASIA']);
    License::factory()->for($this->listed)->create(['type' => 'IZIN-BELUM-TERBIT', 'status' => LicenseStatus::InProcess]);
    $report = MonthlyReport::factory()->for($this->listed)->create(['activity_summary' => 'LAPORAN-RAHASIA', 'income_sales' => 7_777_777]);
    $report->issues()->create(['kind' => IssueKind::Problem, 'category' => 'modal', 'description' => 'MASALAH-RAHASIA']);
    Attachment::factory()->create(['attachable_id' => $this->listed->id, 'original_name' => 'lampiran-rahasia.pdf']);

    foreach ([
        'Kelompok Ditutup' => ['status' => 'closed', 'is_public' => true],
        'Kelompok Tersembunyi' => ['status' => 'active', 'is_public' => false],
    ] as $name => $attributes) {
        $hidden = Organization::factory()->create(['name' => $name, ...$attributes]);
        $hidden->sectors()->attach($this->sector);
        Product::factory()->for($hidden)->create(['name' => 'Produk '.$name, 'is_public' => true, 'is_featured' => true]);
    }
});

dataset('halaman publik', ['/', '/profil', '/direktori', '/produk', '/kontak', '/sitemap.xml', '/direktori/kub-contoh-terbuka']);

test('kelompok ditutup atau tidak publik tidak pernah tampil', function (string $url) {
    $response = $this->get($url)->assertOk();

    foreach (['Kelompok Ditutup', 'Kelompok Tersembunyi', 'kelompok-ditutup', 'kelompok-tersembunyi'] as $hidden) {
        $response->assertDontSee($hidden);
    }
})->with('halaman publik');

test('kelompok tidak aktif sementara yang publik tetap tampil dengan label', function () {
    $idle = Organization::factory()->create(['name' => 'Kelompok Istirahat', 'status' => 'inactive', 'is_public' => true]);

    $this->get('/direktori')->assertOk()->assertSee('Kelompok Istirahat')->assertSee('Tidak aktif sementara');
    $this->get('/direktori/'.$idle->slug)->assertOk()->assertSee('Tidak aktif sementara');
    $this->get('/sitemap.xml')->assertSee($idle->slug);

    $idle->update(['is_public' => false]);
    $this->get('/direktori/'.$idle->slug)->assertNotFound();
});

test('kelompok publik yang ditutup tidak lagi tampil (404)', function () {
    $this->listed->update(['status' => 'closed']);

    $this->get('/direktori/kub-contoh-terbuka')->assertNotFound();
    $this->get('/direktori')->assertDontSee('KUB Contoh Terbuka');
});

test('data privat tidak pernah tampil di halaman publik', function (string $url) {
    $response = $this->get($url)->assertOk();

    foreach ([
        'KETUA-RAHASIA', '0800-1111-2222', 'rahasia@contoh.test', 'ALAMAT-RAHASIA', 'ASET-RAHASIA', 'FASILITAS-RAHASIA',
        '987654', 'CATATAN-RAHASIA', 'NOMOR-IZIN-RAHASIA', 'IZIN-BELUM-TERBIT', 'LAPORAN-RAHASIA', '7.777.777',
        'MASALAH-RAHASIA', 'lampiran-rahasia.pdf', 'Produk Tersembunyi', '/lampiran/',
    ] as $secret) {
        $response->assertDontSee($secret);
    }
})->with('halaman publik');

test('profil kelompok yang tidak terdaftar publik 404', function (string $slug) {
    $this->get("/direktori/{$slug}")->assertNotFound();
})->with(['kelompok-tidak-aktif', 'kelompok-ditutup', 'kelompok-tersembunyi', 'tidak-ada']);

test('kontak kelompok hanya tampil bila diizinkan', function () {
    $this->get('/direktori/kub-contoh-terbuka')->assertSee('Kontak kelompok tidak dipublikasikan.');

    $this->listed->update(['show_contact_publicly' => true]);

    $this->get('/direktori/kub-contoh-terbuka')
        ->assertSee('KETUA-RAHASIA')
        ->assertSee('0800-1111-2222')
        ->assertSee('https://wa.me/6280011112222', false)
        ->assertDontSee('ASET-RAHASIA');
});

test('bagian legalitas hanya tampil untuk status terdaftar atau izin yang sudah terbit', function (string $legalStatus, array $licenses, array $visible, array $hidden) {
    $organization = Organization::factory()->public()->create(['name' => 'KUB Contoh Legal', 'legal_status' => $legalStatus]);
    foreach ($licenses as $type => $status) {
        License::factory()->for($organization)->create(['type' => $type, 'status' => $status]);
    }

    $response = $this->get('/direktori/kub-contoh-legal')->assertOk();

    foreach ($visible as $text) {
        $response->assertSee($text);
    }
    foreach ($hidden as $text) {
        $response->assertDontSee($text);
    }
})->with([
    'terdaftar tanpa izin' => ['registered', [], ['Legalitas', 'Status hukum: terdaftar'], []],
    'dalam proses dengan izin terbit' => ['in_process', ['NIB' => 'issued', 'PIRT' => 'in_process'], ['Legalitas', 'NIB'], ['PIRT', 'Dalam proses', 'Status hukum']],
    'dalam proses tanpa izin terbit' => ['in_process', ['NIB' => 'needs_fix'], [], ['Legalitas', 'NIB', 'Dalam proses']],
    'belum diketahui' => ['unknown', [], [], ['Legalitas', 'Belum diketahui']],
    'belum terdaftar' => ['none', ['PIRT' => 'none'], [], ['Legalitas', 'Belum terdaftar', 'PIRT']],
]);
