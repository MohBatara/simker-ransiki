<?php

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Jayapura'));

    $this->opd = Opd::factory()->create();
    $this->owner = Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);
    $this->owner->opds()->attach($this->opd);
    $this->report = MonthlyReport::factory()->for($this->owner)->create(['period' => '2026-09-01', 'activity_summary' => 'Milik kelompok 1', 'members_active' => 5]);
});

afterEach(fn () => Carbon::setTestNow());

test('kelompok lain mendapat 404 di semua halaman dan aksi laporan', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->kelompok(Organization::factory()->create())->create());
    $id = $this->report->id;

    $this->get("/kelompok/laporan/{$id}")->assertNotFound();
    $this->get("/kelompok/laporan/{$id}/ubah")->assertNotFound();
    $this->patchJson("/kelompok/laporan/{$id}/draf", ['activity_summary' => 'Diubah'])->assertNotFound();
    $this->post("/kelompok/laporan/{$id}/ajukan")->assertNotFound();
    $this->postJson("/lampiran/laporan/{$id}", ['file' => realUpload('a.jpg', imageBytes(10, 10))])->assertNotFound();

    expect($this->report->fresh()->activity_summary)->toBe('Milik kelompok 1')
        ->and($this->report->fresh()->status)->toBe(ReportStatus::Draft);
});

test('daftar laporan kelompok tidak memuat laporan kelompok lain', function () {
    $other = Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);
    $this->actingAs(User::factory()->kelompok($other)->create());

    $this->get('/kelompok/laporan')->assertOk()->assertDontSee("/kelompok/laporan/{$this->report->id}");
});

test('admin dan OPD tidak bisa membuka area laporan kelompok', function (string $role) {
    $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->opd($this->opd)->create();
    $this->actingAs($user);

    $this->get('/kelompok/laporan')->assertForbidden();
    $this->get("/kelompok/laporan/{$this->report->id}/ubah")->assertForbidden();
    $this->patchJson("/kelompok/laporan/{$this->report->id}/draf", ['activity_summary' => 'Diubah'])->assertForbidden();
    $this->post("/kelompok/laporan/{$this->report->id}/ajukan")->assertForbidden();

    expect($this->report->fresh()->activity_summary)->toBe('Milik kelompok 1');
})->with(['admin', 'opd']);

test('OPD pembina membuka detail laporan setelah diajukan, baca-saja; draf dan OPD lain 404', function () {
    $this->actingAs(User::factory()->opd($this->opd)->create());
    $this->get("/opd/laporan/{$this->report->id}")->assertNotFound();

    $this->report->forceFill(['status' => ReportStatus::Submitted, 'submitted_at' => now()])->save();
    $this->get("/opd/laporan/{$this->report->id}")
        ->assertOk()
        ->assertSee('Milik kelompok 1')
        ->assertSee('Riwayat')
        ->assertDontSee('Lanjutkan mengisi');

    $this->actingAs(User::factory()->opd(Opd::factory()->create())->create());
    $this->get("/opd/laporan/{$this->report->id}")->assertNotFound();
});
