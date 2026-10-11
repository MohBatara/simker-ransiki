<?php

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ReportSubmitted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jayapura'));

    $this->opd = Opd::factory()->create(['short_name' => 'Dinkopdag']);
    $this->organization = Organization::factory()->create(['name' => 'KUB Tanpa HP', 'reporting_starts_on' => '2026-07-01']);
    $this->organization->opds()->attach($this->opd);
    $this->inputter = User::factory()->opd($this->opd)->create(['name' => 'Petugas Pengisi']);
    $this->admin = User::factory()->admin()->create(['name' => 'Admin Distrik']);
});

afterEach(fn () => Carbon::setTestNow());

/** OPD mengisi laporan September atas nama kelompok lalu mengajukannya. */
function enterOnBehalf(object $test): MonthlyReport
{
    $test->actingAs($test->inputter)
        ->post("/opd/kelompok/{$test->organization->id}/laporan", ['period' => '2026-09'])
        ->assertRedirect();
    $report = $test->organization->monthlyReports()->firstOrFail();

    $test->patchJson("/opd/laporan/{$report->id}/draf", ['activity_summary' => 'Panen sagu', 'members_active' => '8'])->assertOk();
    $test->post("/opd/laporan/{$report->id}/ajukan")->assertSessionHasNoErrors();

    return $report->fresh();
}

test('OPD pembina mengisi laporan atas nama kelompok; pengisi tercatat dan tampil', function () {
    $this->actingAs($this->inputter)->get("/opd/kelompok/{$this->organization->id}/laporan")
        ->assertOk()->assertSee('Isi laporan atas nama kelompok');

    $this->post("/opd/kelompok/{$this->organization->id}/laporan", ['period' => '2026-09'])
        ->assertRedirect('/opd/laporan/'.MonthlyReport::firstOrFail()->id.'/ubah');
    $report = MonthlyReport::firstOrFail();

    $this->get("/opd/laporan/{$report->id}/ubah")->assertOk()->assertSee('Mengisi atas nama kelompok')->assertSee('KUB Tanpa HP');
    $this->patchJson("/opd/laporan/{$report->id}/draf", ['activity_summary' => 'Panen sagu', 'members_active' => '8'])->assertOk();
    $this->patchJson("/opd/laporan/{$report->id}/draf", ['activity_summary' => 'Panen sagu lagi', 'members_active' => '8'])->assertOk();

    $report->refresh();
    expect($report->entered_by)->toBe($this->inputter->id)
        ->and($report->activity_summary)->toBe('Panen sagu lagi')
        // Dicatat sekali per akun, bukan setiap simpan otomatis.
        ->and($report->reviews()->where('action', ReviewAction::Entered)->count())->toBe(1);

    $this->post("/opd/laporan/{$report->id}/ajukan")
        ->assertRedirect("/opd/laporan/{$report->id}?dari=kelompok")
        ->assertSessionHas('status', 'Laporan diajukan. Perlu diverifikasi oleh Admin atau petugas OPD lain.');

    expect($report->fresh()->status)->toBe(ReportStatus::Submitted)
        ->and($report->fresh()->submitted_by)->toBe($this->inputter->id);

    // Kelompok yang punya akun juga melihat siapa yang mengisi.
    $this->actingAs(User::factory()->kelompok($this->organization)->create())
        ->get("/kelompok/laporan/{$report->id}")->assertOk()->assertSee('Diisi oleh')->assertSee('Petugas Pengisi (Dinkopdag)');
});

test('OPD lain dan kelompok lain tidak bisa mengisi atas nama kelompok non-binaan', function () {
    $this->actingAs(User::factory()->opd(Opd::factory()->create())->create())
        ->post("/opd/kelompok/{$this->organization->id}/laporan", ['period' => '2026-09'])
        ->assertNotFound();

    $this->actingAs(User::factory()->kelompok($this->organization)->create())
        ->post("/opd/kelompok/{$this->organization->id}/laporan", ['period' => '2026-09'])
        ->assertForbidden();

    expect(MonthlyReport::count())->toBe(0);
});

test('draf yang sedang diisi kelompok tetap tertutup bagi OPD', function () {
    $draft = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01', 'entered_by' => User::factory()->kelompok($this->organization)->create()->id]);

    $this->actingAs($this->inputter)
        ->from("/opd/kelompok/{$this->organization->id}/laporan")
        ->post("/opd/kelompok/{$this->organization->id}/laporan", ['period' => '2026-09'])
        ->assertRedirect("/opd/kelompok/{$this->organization->id}/laporan")
        ->assertSessionHas('error');

    $this->get("/opd/laporan/{$draft->id}/ubah")->assertNotFound();
    $this->patchJson("/opd/laporan/{$draft->id}/draf", ['activity_summary' => 'Diubah'])->assertNotFound();
});

test('penginput tidak bisa memverifikasi atau meminta revisi, termasuk lewat request langsung', function () {
    $report = enterOnBehalf($this);

    $this->get("/opd/laporan/{$report->id}")->assertOk()
        ->assertSee('Perlu diverifikasi oleh Admin atau petugas OPD lain')
        ->assertDontSee('id="form-verify"', false);
    $this->get('/opd/verifikasi')->assertSee('Perlu diverifikasi oleh Admin atau petugas OPD lain');

    $this->post("/opd/laporan/{$report->id}/verifikasi", ['verify_note' => ''])->assertForbidden();
    $this->post("/opd/laporan/{$report->id}/minta-revisi", ['revision_note' => 'Mohon diperbaiki angkanya.'])->assertForbidden();

    expect($report->fresh()->status)->toBe(ReportStatus::Submitted)
        ->and($report->reviews()->whereIn('action', [ReviewAction::Verified, ReviewAction::RevisionRequested])->count())->toBe(0);
});

test('admin yang ikut mengisi juga tidak bisa memverifikasi laporan itu', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01', 'entered_by' => $this->admin->id, 'activity_summary' => 'Isi', 'members_active' => 3]);
    $this->actingAs($this->admin)->post("/admin/laporan/{$report->id}/ajukan")->assertSessionHasNoErrors();

    $this->post("/admin/laporan/{$report->id}/verifikasi")->assertForbidden();
    expect($report->fresh()->status)->toBe(ReportStatus::Submitted);
});

test('petugas OPD lain yang terhubung dan Admin bisa memverifikasi', function (string $who) {
    $report = enterOnBehalf($this);
    $verifier = $who === 'opd lain' ? User::factory()->opd($this->opd)->create() : $this->admin;
    $area = $verifier->role->area();

    $this->actingAs($verifier)->get("/{$area}/laporan/{$report->id}")->assertOk()->assertSee('id="form-verify"', false);
    $this->post("/{$area}/laporan/{$report->id}/verifikasi", ['verify_note' => ''])->assertSessionHas('status', 'Laporan diverifikasi.');

    expect($report->fresh()->status)->toBe(ReportStatus::Verified)
        ->and($report->fresh()->verified_by)->toBe($verifier->id);
})->with(['opd lain', 'admin']);

test('OPD dengan satu petugas: laporan masuk antrean verifikasi Admin dan notifikasi ke Admin', function () {
    Notification::fake();
    $other = Organization::factory()->create(['name' => 'KUB Biasa', 'reporting_starts_on' => '2026-07-01']);
    $other->opds()->attach($this->opd);
    $normal = MonthlyReport::factory()->for($other)->status(ReportStatus::Submitted)->create(['submitted_at' => now()]);

    $report = enterOnBehalf($this);

    Notification::assertSentTo($this->admin, ReportSubmitted::class);
    Notification::assertNotSentTo($this->inputter, ReportSubmitted::class);

    $queue = $this->actingAs($this->admin)->get('/admin/verifikasi')->assertOk()->assertSee('KUB Tanpa HP')->viewData('reports');
    expect($queue->pluck('id')->all())->toBe([$report->id])
        ->and($queue->pluck('id')->all())->not->toContain($normal->id);

    // Begitu ada petugas OPD lain, laporan tidak lagi menunggu Admin.
    User::factory()->opd($this->opd)->create();
    expect($this->get('/admin/verifikasi')->viewData('reports')->total())->toBe(0);
});

test('aturan bisa dimatikan lewat config', function () {
    config(['simker.verification.separate_duties' => false]);
    $report = enterOnBehalf($this);

    $this->get("/opd/laporan/{$report->id}")->assertDontSee('Perlu diverifikasi oleh Admin atau petugas OPD lain');
    $this->post("/opd/laporan/{$report->id}/verifikasi", ['verify_note' => ''])->assertSessionHas('status', 'Laporan diverifikasi.');

    expect($report->fresh()->status)->toBe(ReportStatus::Verified);
});

test('kelompok yang mengajukan sendiri tetap bisa diverifikasi OPD pembina', function () {
    $member = User::factory()->kelompok($this->organization)->create();
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01', 'entered_by' => $member->id, 'activity_summary' => 'Isi', 'members_active' => 3]);
    $this->actingAs($member)->post("/kelompok/laporan/{$report->id}/ajukan")->assertSessionHasNoErrors();

    $this->actingAs($this->inputter)->post("/opd/laporan/{$report->id}/verifikasi")->assertSessionHas('status', 'Laporan diverifikasi.');
});
