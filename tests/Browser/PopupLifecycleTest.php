<?php

use App\Enums\IssueKind;
use App\Enums\LicenseStatus;
use App\Enums\ProfileStatus;
use App\Enums\ReportStatus;
use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

/*
 * Siklus setiap popup di area Admin dan OPD dengan klik sungguhan: buka, tutup dengan X, buka lagi lalu tutup dengan
 * Batal/Tutup, buka lagi lalu tutup dengan Esc, lalu buka popup itu sekali lagi (popup kedua setelah yang pertama
 * ditutup). Setelah tertutup, halaman harus bisa digulir lagi. Gagal bila ada error JavaScript atau pesan console.
 */
beforeEach(function () {
    $this->opd = Opd::factory()->create(['name' => 'Dinas Uji Peramban']);
    $this->organization = Organization::factory()->create([
        'name' => 'KUB Peramban', 'profile_status' => ProfileStatus::Unverified, 'reporting_starts_on' => now()->subMonths(3)->startOfMonth(),
    ]);
    $this->organization->opds()->attach($this->opd);
    $this->product = Product::factory()->for($this->organization)->create(['name' => 'Kakao Uji']);
    License::factory()->for($this->organization)->create(['type' => 'PIRT', 'status' => LicenseStatus::InProcess]);
    $this->report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Submitted)->create([
        'period' => now()->subMonth()->startOfMonth(), 'submitted_at' => now(),
    ]);
    $this->issue = $this->report->issues()->create(['kind' => IssueKind::Support, 'category' => 'pelatihan', 'description' => 'Pelatihan pengemasan.']);
    $this->member = User::factory()->create(['name' => 'Pengguna Uji']);
    $this->admin = User::factory()->admin()->create();
});

test('popup terbuka dan tertutup dengan X, Batal/Tutup, Esc, lalu bisa dibuka lagi', function (string $role, Closure $path, array $open, string $name, ?string $cancel) {
    $user = $role === 'opd' ? User::factory()->opd($this->opd)->create() : $this->admin;
    $this->actingAs($user);
    $dialog = 'dialog[data-modal="'.$name.'"]';

    $page = visit($path->call($this));

    // Tutup dengan X.
    openPopup($page, $open, $name);
    assertPopupClosed($page->click($dialog.' button[aria-label="Tutup"]')->wait(0.3), $name);

    // Tutup dengan Batal/Tutup di kaki popup.
    if ($cancel) {
        openPopup($page, $open, $name);
        assertPopupClosed($page->click($dialog.' footer button:has-text("'.$cancel.'")')->wait(0.3), $name);
    }

    // Tutup dengan Esc, lalu buka sekali lagi.
    openPopup($page, $open, $name);
    assertPopupClosed($page->keys($dialog, 'Escape')->wait(0.3), $name);
    openPopup($page, $open, $name);

    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with([
    'filter daftar kelompok' => ['admin', fn () => '/admin/kelompok', ['button[aria-controls="panel-filter"]'], 'filter', 'Batal'],
    'filter daftar laporan' => ['admin', fn () => '/admin/laporan', ['button[aria-controls="panel-filter"]'], 'filter', 'Batal'],
    'cari umum' => ['admin', fn () => '/admin/kelompok', ['button[data-search-open]'], 'cari-global', null],
    'pratinjau kelompok' => ['admin', fn () => '/admin/kelompok', ['a[data-row-link]:has-text("KUB Peramban")'], 'organization-preview', 'Tutup'],
    'ubah status kelompok' => ['admin', fn () => "/admin/kelompok/{$this->organization->id}", ['button[aria-label="Tindakan lain"]', 'button:has-text("Ubah status") >> nth=0'], 'organization-status', 'Batal'],
    'tambah akun kelompok' => ['admin', fn () => "/admin/kelompok/{$this->organization->id}", ['button[aria-label="Tindakan lain"]', 'button:has-text("Tambah akun") >> nth=0'], 'user-create', 'Batal'],
    'tandai profil terverifikasi' => ['admin', fn () => "/admin/kelompok/{$this->organization->id}", ['button:has-text("Tandai terverifikasi") >> nth=0'], 'verify-profile', 'Batal'],
    'tambah produk' => ['admin', fn () => "/admin/kelompok/{$this->organization->id}/produk", ['header button:has-text("Tambah produk")'], 'record-form', 'Batal'],
    'hapus produk' => ['admin', fn () => "/admin/kelompok/{$this->organization->id}/produk", ['button[aria-label="Tindakan untuk Kakao Uji"]', 'button:has-text("Hapus") >> nth=0'], 'record-delete', 'Batal'],
    'tambah izin' => ['admin', fn () => "/admin/kelompok/{$this->organization->id}/izin", ['header button:has-text("Tambah izin")'], 'record-form', 'Batal'],
    'isi laporan atas nama kelompok' => ['admin', fn () => "/admin/kelompok/{$this->organization->id}/laporan", ['header button:has-text("Isi laporan")'], 'report-entry', 'Batal'],
    'tambah akun' => ['admin', fn () => '/admin/pengaturan/pengguna', ['header button:has-text("Tambah akun")'], 'user-create', 'Batal'],
    'ubah akun' => ['admin', fn () => "/admin/pengaturan/pengguna/{$this->member->id}", ['header button:has-text("Ubah akun")'], 'user-edit', 'Batal'],
    'atur ulang sandi' => ['admin', fn () => "/admin/pengaturan/pengguna/{$this->member->id}", ['header button:has-text("Atur ulang sandi")'], 'reset-password', 'Batal'],
    'nonaktifkan akun' => ['admin', fn () => "/admin/pengaturan/pengguna/{$this->member->id}", ['button:has-text("Nonaktifkan akun") >> nth=0'], 'deactivate-user', 'Batal'],
    'tambah data master' => ['admin', fn () => '/admin/pengaturan/data-master/kampung', ['header button:has-text("Tambah kampung")'], 'record-form', 'Batal'],
    'unduh rekap' => ['admin', fn () => '/admin/laporan', ['header button:has-text("Unduh rekap")'], 'report-recap', 'Batal'],
    'verifikasi laporan' => ['admin', fn () => "/admin/laporan/{$this->report->id}", ['button:has-text("Verifikasi") >> nth=-1'], 'report-verify', 'Batal'],
    'minta revisi' => ['admin', fn () => "/admin/laporan/{$this->report->id}", ['button:has-text("Minta revisi") >> nth=-1'], 'report-revision', 'Batal'],
    'tindak lanjut masalah' => ['admin', fn () => "/admin/laporan/masalah/{$this->issue->id}", ['header button:has-text("Ubah status tindak lanjut")'], 'follow-up', 'Batal'],
    'pratinjau kelompok binaan (OPD)' => ['opd', fn () => '/opd/kelompok', ['a[data-row-link]:has-text("KUB Peramban")'], 'organization-preview', 'Tutup'],
    'filter (OPD)' => ['opd', fn () => '/opd/kelompok', ['button[aria-controls="panel-filter"]'], 'filter', 'Batal'],
    'tindak lanjut (OPD)' => ['opd', fn () => "/opd/laporan/masalah/{$this->issue->id}", ['header button:has-text("Ubah status tindak lanjut")'], 'follow-up', 'Batal'],
]);
