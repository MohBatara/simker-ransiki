<?php

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\ReportReview;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jayapura'));

    $this->opd = Opd::factory()->create();
    $this->otherOpd = Opd::factory()->create();
    $this->ours = Organization::factory()->create(['name' => 'KUB Binaan Satu']);
    $this->ours->opds()->attach($this->opd);
    $this->second = Organization::factory()->create(['name' => 'KUB Binaan Dua']);
    $this->second->opds()->attach($this->opd);
    $this->foreign = Organization::factory()->create(['name' => 'KUB Bukan Binaan']);
    $this->foreign->opds()->attach($this->otherOpd);
    $this->reviewer = User::factory()->opd($this->opd)->create();
});

afterEach(fn () => Carbon::setTestNow());

/** Laporan dengan riwayat pengajuan pada waktu tertentu. */
function submittedReport(Organization $organization, string $period, string $submittedAt, array $history = []): MonthlyReport
{
    $report = MonthlyReport::factory()->for($organization)->status(ReportStatus::Submitted)->create(['period' => $period, 'submitted_at' => $submittedAt]);

    foreach ([[ReviewAction::Submitted, $submittedAt], ...$history] as [$action, $at]) {
        ReportReview::forceCreate(['monthly_report_id' => $report->id, 'action' => $action, 'created_at' => $at]);
    }

    return $report;
}

test('antrean OPD hanya berisi laporan diajukan milik binaan, yang paling lama menunggu di atas', function () {
    $newer = submittedReport($this->ours, '2026-09-01', '2026-10-18 09:00:00');
    // Pengajuan pertama lebih awal, tetapi diajukan ulang setelah revisi: urutan memakai pengajuan terakhir.
    $resubmitted = submittedReport($this->second, '2026-09-01', '2026-10-03 09:00:00', [
        [ReviewAction::RevisionRequested, '2026-10-04 09:00:00'],
        [ReviewAction::Submitted, '2026-10-19 08:00:00'],
    ]);
    $older = submittedReport($this->second, '2026-08-01', '2026-10-06 09:00:00');
    MonthlyReport::factory()->for($this->ours)->create(['period' => '2026-08-01']);
    MonthlyReport::factory()->for($this->ours)->status(ReportStatus::Verified)->create(['period' => '2026-07-01', 'submitted_at' => now()]);
    submittedReport($this->foreign, '2026-09-01', '2026-10-01 09:00:00');

    $response = $this->actingAs($this->reviewer)->get('/opd/verifikasi')->assertOk();

    expect($response->viewData('reports')->pluck('id')->all())->toBe([$older->id, $newer->id, $resubmitted->id]);
    $response->assertSee('14 hari')
        ->assertSee('Diajukan ulang')
        ->assertDontSee('KUB Bukan Binaan')
        ->assertSee('aria-label="Verifikasi Laporan"', false);
});

test('antrean kosong menampilkan penjelasan', function () {
    $this->actingAs($this->reviewer)->get('/opd/verifikasi')->assertOk()->assertSee('Tidak ada laporan yang menunggu verifikasi');
});

test('semua laporan OPD: draf tampil sebagai "Draf, belum diajukan" tanpa tautan', function () {
    $draft = MonthlyReport::factory()->for($this->ours)->create(['period' => '2026-09-01']);
    $submitted = submittedReport($this->second, '2026-09-01', '2026-10-05 09:00:00');
    submittedReport($this->foreign, '2026-09-01', '2026-10-05 09:00:00');

    $this->actingAs($this->reviewer)->get('/opd/laporan')
        ->assertOk()
        ->assertSee('Draf, belum diajukan')
        ->assertSee("/opd/laporan/{$submitted->id}")
        ->assertDontSee("/opd/laporan/{$draft->id}\"", false)
        ->assertDontSee('KUB Bukan Binaan');

    $this->get("/opd/laporan/{$draft->id}")->assertNotFound();
});

test('filter semua laporan: status, periode, dan nama kelompok', function () {
    $draft = MonthlyReport::factory()->for($this->ours)->create(['period' => '2026-09-01']);
    $submitted = submittedReport($this->second, '2026-08-01', '2026-09-05 09:00:00');
    $this->actingAs($this->reviewer);

    expect($this->get('/opd/laporan?status=submitted')->viewData('reports')->pluck('id')->all())->toBe([$submitted->id])
        ->and($this->get('/opd/laporan?period=2026-09')->viewData('reports')->pluck('id')->all())->toBe([$draft->id])
        ->and($this->get('/opd/laporan?q=Dua')->viewData('reports')->pluck('id')->all())->toBe([$submitted->id])
        ->and($this->get('/opd/laporan?period=bukan')->viewData('reports'))->toHaveCount(2);
});

test('admin melihat semua laporan distrik dan bisa menyaring per OPD', function () {
    $ours = submittedReport($this->ours, '2026-09-01', '2026-10-05 09:00:00');
    $foreign = submittedReport($this->foreign, '2026-09-01', '2026-10-05 09:00:00');
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/laporan')->assertOk()->assertSee('KUB Binaan Satu')->assertSee('KUB Bukan Binaan')->assertSee('Semua OPD');
    expect($this->get("/admin/laporan?opd={$this->otherOpd->id}")->viewData('reports')->pluck('id')->all())->toBe([$foreign->id]);
    $this->get("/admin/laporan/{$ours->id}")->assertOk();
});

test('tab laporan di detail kelompok bisa diklik admin, kecuali draf yang belum diajukan', function () {
    $draft = MonthlyReport::factory()->for($this->ours)->create(['period' => '2026-09-01']);
    $submitted = submittedReport($this->ours, '2026-08-01', '2026-09-05 09:00:00');

    $this->actingAs(User::factory()->admin()->create())
        ->get("/admin/kelompok/{$this->ours->id}/laporan")
        ->assertOk()
        ->assertSee("/admin/laporan/{$submitted->id}")
        ->assertDontSee("/admin/laporan/{$draft->id}\"", false)
        ->assertSee('Draf, belum diajukan');
});

test('peran lain tidak bisa membuka daftar laporan peninjau', function () {
    $this->actingAs(User::factory()->kelompok($this->ours)->create());
    $this->get('/opd/verifikasi')->assertForbidden();
    $this->get('/opd/laporan')->assertForbidden();
    $this->get('/admin/laporan')->assertForbidden();

    $this->actingAs($this->reviewer)->get('/admin/laporan')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/opd/verifikasi')->assertForbidden();
});
