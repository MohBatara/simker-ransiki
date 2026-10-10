<?php

use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-04 09:00:00', 'Asia/Jayapura'));
    $this->organization = Organization::factory()->create(['name' => 'KUB Milik', 'reporting_starts_on' => '2026-07-01', 'description' => null]);
    $this->actingAs(User::factory()->kelompok($this->organization)->create());
});

afterEach(fn () => Carbon::setTestNow());

test('belum ada laporan bulan wajib lapor: status, tenggat dalam kalimat, tombol buat', function () {
    $this->get('/kelompok')
        ->assertOk()
        ->assertSee('Laporan September 2026')
        ->assertSee('Belum dibuat')
        ->assertSee('Tenggat 10 Oktober 2026 · 6 hari lagi')
        ->assertSee('value="2026-09"', false)
        ->assertSee('Buat laporan')
        ->assertSee('2 bulan sebelumnya belum diajukan')
        ->assertDontSee('sudah bisa mulai diisi');
});

test('tombol mengikuti status laporan', function (ReportStatus $status, string $button, string $path) {
    $report = MonthlyReport::factory()->for($this->organization)->status($status)->create([
        'period' => '2026-09-01', 'submitted_at' => $status === ReportStatus::Draft ? null : '2026-10-02 10:00:00',
    ]);

    $this->get('/kelompok')->assertOk()->assertSee($button)->assertSee("/kelompok/laporan/{$report->id}{$path}\"", false);
})->with([
    'draf' => [ReportStatus::Draft, 'Lanjutkan', '/ubah'],
    'revisi' => [ReportStatus::Revision, 'Perbaiki laporan', '/ubah'],
    'diajukan' => [ReportStatus::Submitted, 'Lihat laporan', ''],
    'terverifikasi' => [ReportStatus::Verified, 'Lihat laporan', ''],
]);

test('setelah diajukan, bulan berjalan ditawarkan sebagai draf', function () {
    MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => '2026-10-02 10:00:00']);

    $this->get('/kelompok')
        ->assertSee('Diajukan 2 Oktober 2026')
        ->assertSee('Laporan Oktober 2026 sudah bisa mulai diisi sebagai draf.')
        ->assertSee('value="2026-10"', false);

    $current = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-10-01']);
    $this->get('/kelompok')->assertSee("/kelompok/laporan/{$current->id}/ubah\"", false);
});

test('lewat tenggat ditulis jelas', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00', 'Asia/Jayapura'));

    $this->get('/kelompok')->assertSee('Tenggat 10 Oktober 2026 · lewat 3 hari');
});

test('catatan revisi terbaru tampil dengan tombol perbaiki', function () {
    $report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Revision)->create(['period' => '2026-08-01', 'submitted_at' => '2026-09-05 10:00:00']);
    $reviewer = User::factory()->opd(Opd::factory()->create())->create(['name' => 'Petugas Contoh']);
    $report->reviews()->create(['user_id' => $reviewer->id, 'action' => ReviewAction::RevisionRequested, 'note' => 'Lengkapi foto kegiatan.']);

    $this->get('/kelompok')
        ->assertSee('Perlu revisi: Laporan Agustus 2026')
        ->assertSee('Lengkapi foto kegiatan.')
        ->assertSee('Petugas Contoh');
});

test('kelompok tutup tidak ditawari membuat laporan', function () {
    $this->organization->update(['status' => OrganizationStatus::Closed]);

    $this->get('/kelompok')->assertOk()->assertSee('sudah tutup')->assertDontSee('Buat laporan');
});

test('kelompok yang kewajibannya belum dimulai', function () {
    $this->organization->update(['reporting_starts_on' => '2026-10-01']);

    $this->get('/kelompok')->assertOk()
        ->assertSee('Kewajiban lapor dimulai Oktober 2026')
        ->assertSee('Laporan Oktober 2026 sudah bisa mulai diisi sebagai draf.');
});

test('kelengkapan profil dan riwayat hanya milik sendiri, dengan jumlah query terbatas', function () {
    $other = Organization::factory()->create(['name' => 'KUB Lain']);
    MonthlyReport::factory()->for($other)->status(ReportStatus::Verified)->create(['period' => '2026-09-01', 'submitted_at' => now()]);
    MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Verified)->create(['period' => '2026-07-01', 'submitted_at' => '2026-08-12 10:00:00']);

    DB::enableQueryLog();
    $response = $this->get('/kelompok');
    $queries = count(DB::getQueryLog());

    $response->assertOk()
        ->assertSee('0 dari 4')
        ->assertSee('Deskripsi kelompok')
        ->assertSeeInOrder(['September 2026', 'Agustus 2026', 'Juli 2026'])
        ->assertSee('Diajukan terlambat')
        ->assertDontSee('KUB Lain');
    expect($queries)->toBeLessThan(20);
});
