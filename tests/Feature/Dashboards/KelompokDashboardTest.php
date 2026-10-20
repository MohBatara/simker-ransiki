<?php

use App\Enums\LegalStatus;
use App\Enums\OrganizationStatus;
use App\Enums\ReportingState;
use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-04 09:00:00', 'Asia/Jayapura'));
    $this->organization = Organization::factory()->create(['name' => 'KUB Milik', 'reporting_starts_on' => '2026-07-01', 'description' => null]);
    $this->actingAs(User::factory()->kelompok($this->organization)->create());
});

afterEach(fn () => Carbon::setTestNow());

/** Isi data-task dan judul tugas utama di kartu sapaan. */
function primaryTask(string $html): array
{
    preg_match('/data-task="([^"]+)">([^<]+)</', $html, $match);

    return [$match[1] ?? null, html_entity_decode(trim($match[2] ?? ''))];
}

test('belum diajukan dan belum lewat tenggat: paling lambat + hari lagi, tombol Isi laporan', function () {
    $html = $this->get('/kelompok')->assertOk()
        ->assertSee('value="2026-09"', false)
        // Juli dan Agustus belum pernah diajukan dan sudah lewat tenggat: Terlambat di daftar Laporan saya.
        ->assertSeeInOrder(['Laporan saya', 'September 2026', 'Belum diajukan', 'Agustus 2026', 'Terlambat', 'Juli 2026', 'Terlambat'])
        ->assertDontSee('sudah bisa diisi')
        ->getContent();

    expect(primaryTask($html))->toBe(['open', 'Laporan September 2026 paling lambat 10 Oktober 2026 (6 hari lagi)'])
        ->and(preg_match_all('/>\s*Isi laporan\s*</', $html))->toBe(1)
        ->and($html)->toContain('data-tone="brand"');
});

test('belum diajukan dan lewat tenggat: terlambat N hari, kartu merah, tombol Isi laporan sekarang', function (string $now, int $days, ?ReportStatus $draft) {
    Carbon::setTestNow(Carbon::parse($now, 'Asia/Jayapura'));
    if ($draft) {
        MonthlyReport::factory()->for($this->organization)->status($draft)->create(['period' => '2026-09-01', 'submitted_at' => null]);
    }

    $html = $this->get('/kelompok')->assertOk()->getContent();

    expect(primaryTask($html))->toBe(['overdue', "Laporan September 2026 terlambat {$days} hari"])
        ->and($html)->toContain('data-tone="danger"')
        ->and(substr_count($html, 'Isi laporan sekarang'))->toBe(1);
})->with([
    'sehari setelah tenggat, belum dibuat' => ['2026-10-11 08:00:00', 1, null],
    '10 hari, masih draf' => ['2026-10-20 12:00:00', 10, ReportStatus::Draft],
]);

test('diajukan tepat waktu lalu diminta revisi: bukan terlambat; tugas utama perbaiki dengan catatan OPD', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jayapura'));
    $report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Revision)->create(['period' => '2026-09-01', 'submitted_at' => '2026-10-08 10:00:00']);
    $reviewer = User::factory()->opd(Opd::factory()->create(['name' => 'Dinas Contoh', 'short_name' => 'Contoh']))->create(['name' => 'Petugas Contoh']);
    $report->reviews()->forceCreate(['user_id' => $reviewer->id, 'action' => ReviewAction::RevisionRequested, 'note' => 'Lengkapi foto kegiatan.', 'created_at' => '2026-10-15 10:00:00']);

    $html = $this->get('/kelompok')->assertOk()
        ->assertSee('Lengkapi foto kegiatan.')
        ->assertSee('Catatan dari Petugas Contoh')
        ->assertSee('15 Oktober 2026')
        ->assertSee("/kelompok/laporan/{$report->id}/ubah\"", false)
        ->assertDontSee('terlambat')
        ->assertDontSee('lewat ')
        ->getContent();

    expect(primaryTask($html))->toBe(['revision', 'Laporan September 2026 perlu diperbaiki'])
        ->and($html)->toContain('data-tone="danger"')
        ->and(preg_match_all('/>\s*Perbaiki laporan\s*</', $html))->toBe(1)
        ->and(ReportingState::for($report, $report->period))->toBe(ReportingState::Revision)
        ->and($report->isOverdue())->toBeFalse();
});

test('revisi didahulukan dari laporan lain yang terlambat', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jayapura'));
    MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Revision)->create(['period' => '2026-08-01', 'submitted_at' => '2026-09-05 10:00:00']);

    expect(primaryTask($this->get('/kelompok')->getContent()))->toBe(['revision', 'Laporan Agustus 2026 perlu diperbaiki']);
});

test('sudah diajukan menunggu: tanpa tombol utama; terverifikasi: terima kasih; bulan berjalan ditawarkan', function (ReportStatus $status, string $kind, string $title) {
    MonthlyReport::factory()->for($this->organization)->status($status)->create(['period' => '2026-09-01', 'submitted_at' => '2026-10-02 10:00:00']);

    $html = $this->get('/kelompok')->assertOk()
        ->assertSee('Diajukan 2 Okt 2026')
        ->assertSee('Laporan Oktober 2026 sudah bisa diisi.')
        ->assertSee('value="2026-10"', false)
        ->getContent();

    expect(primaryTask($html))->toBe([$kind, $title])
        ->and($html)->toContain('data-tone="brand"')
        ->and($html)->not->toContain('Isi laporan');
})->with([
    'menunggu' => [ReportStatus::Submitted, 'waiting', 'Laporan September 2026 sedang diperiksa OPD'],
    'terverifikasi' => [ReportStatus::Verified, 'done', 'Laporan September 2026 sudah terverifikasi. Terima kasih.'],
]);

test('draf bulan berjalan: Lanjutkan', function () {
    MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => '2026-10-02 10:00:00']);
    $current = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-10-01']);

    $this->get('/kelompok')->assertSee('Lanjutkan')->assertSee("/kelompok/laporan/{$current->id}/ubah\"", false);
});

test('kelompok tutup tidak ditawari membuat laporan', function () {
    $this->organization->update(['status' => OrganizationStatus::Closed]);

    $this->get('/kelompok')->assertOk()->assertDontSee('name="period"', false);
});

test('kelompok yang kewajibannya belum dimulai', function () {
    $this->organization->update(['reporting_starts_on' => '2026-10-01']);

    $html = $this->get('/kelompok')->assertOk()->assertSee('Laporan Oktober 2026 sudah bisa diisi.')->getContent();

    expect(primaryTask($html))->toBe(['not-started', 'Kewajiban lapor dimulai Oktober 2026.']);
});

test('laporan saya: tiga bulan terakhir dengan status dan tanggal diajukan; hanya milik sendiri', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jayapura'));
    $other = Organization::factory()->create(['name' => 'KUB Lain']);
    MonthlyReport::factory()->for($other)->status(ReportStatus::Verified)->create(['period' => '2026-09-01', 'submitted_at' => now()]);
    MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Verified)->create(['period' => '2026-07-01', 'submitted_at' => '2026-08-12 10:00:00']);
    MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Revision)->create(['period' => '2026-08-01', 'submitted_at' => '2026-09-05 10:00:00']);

    DB::enableQueryLog();
    $response = $this->get('/kelompok');
    $queries = count(DB::getQueryLog());

    $response->assertOk()
        ->assertSeeInOrder(['Laporan saya', 'September 2026', 'Belum diajukan', 'Terlambat', 'Agustus 2026', 'Diajukan 5 Sep 2026', 'Perlu revisi', 'Juli 2026', 'Diajukan 12 Agt 2026, terlambat', 'Terverifikasi'])
        ->assertDontSee('KUB Lain');
    expect($queries)->toBeLessThan(20);
});

test('kelengkapan profil: daftar bila belum lengkap, satu baris bila lengkap', function () {
    $this->get('/kelompok')->assertSee('Kelengkapan profil')->assertSee('0 dari 4')->assertSee('Deskripsi kelompok');

    $this->organization->update(['description' => 'Contoh deskripsi.', 'legal_status' => LegalStatus::None]);
    Product::factory()->for($this->organization)->create(['photo_path' => 'produk/contoh.jpg']);

    $this->get('/kelompok')
        ->assertSee('data-profile-complete', false)
        ->assertSee('Profil lengkap')
        ->assertSee('Lihat profil')
        ->assertDontSee('Kelengkapan profil')
        ->assertDontSee('Deskripsi kelompok');
});
