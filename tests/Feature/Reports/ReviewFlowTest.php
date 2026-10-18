<?php

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\Attachment;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jayapura'));

    $this->opd = Opd::factory()->create();
    $this->organization = Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);
    $this->organization->opds()->attach($this->opd);
    $this->reviewer = User::factory()->opd($this->opd)->create(['name' => 'Petugas Pertanian']);
    $this->member = User::factory()->kelompok($this->organization)->create();
    $this->admin = User::factory()->admin()->create();
    $this->report = MonthlyReport::factory()->for($this->organization)->create([
        'period' => '2026-09-01', 'activity_summary' => 'Panen kakao', 'members_active' => 10,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

test('siklus lengkap: ajukan, minta revisi, perbaiki, ajukan ulang, verifikasi', function () {
    $id = $this->report->id;

    $this->actingAs($this->member)->post("/kelompok/laporan/{$id}/ajukan")->assertSessionHasNoErrors();

    $this->actingAs($this->reviewer)->get("/opd/laporan/{$id}")
        ->assertOk()->assertSee('Minta revisi')->assertSee('Verifikasi')->assertDontSee('Buka kembali');

    $this->post("/opd/laporan/{$id}/minta-revisi", ['revision_note' => 'Isi angka biaya bulan ini.'])
        ->assertRedirect("/opd/laporan/{$id}")
        ->assertSessionHas('status', 'Revisi diminta. Kelompok mendapat notifikasi.');
    expect($this->report->fresh()->status)->toBe(ReportStatus::Revision);

    $this->actingAs($this->member)->get("/kelompok/laporan/{$id}/ubah")
        ->assertOk()->assertSee('Isi angka biaya bulan ini.')->assertSee('Petugas Pertanian');
    $this->patchJson("/kelompok/laporan/{$id}/draf", ['activity_summary' => 'Panen kakao', 'members_active' => '10', 'costs' => '150.000'])->assertOk();
    $this->post("/kelompok/laporan/{$id}/ajukan")->assertSessionHasNoErrors();

    $this->actingAs($this->reviewer)->get('/opd/laporan/verifikasi')->assertSee('Diajukan ulang');
    $this->post("/opd/laporan/{$id}/verifikasi", ['verify_note' => ''])
        ->assertSessionHas('status', 'Laporan diverifikasi.');

    $report = $this->report->fresh();
    expect($report->status)->toBe(ReportStatus::Verified)
        ->and($report->verified_by)->toBe($this->reviewer->id)
        ->and($report->reviews()->orderBy('id')->pluck('action')->map->value->all())
        ->toBe(['submitted', 'revision_requested', 'submitted', 'verified']);

    $this->get("/opd/laporan/{$id}")->assertOk()->assertDontSee('Minta revisi')->assertSee('oleh Petugas Pertanian');
    $this->actingAs($this->member)->get("/kelompok/laporan/{$id}")->assertSee('Terverifikasi')->assertDontSee('Lanjutkan mengisi');
});

test('catatan revisi wajib dan minimal 10 karakter; modal terbuka lagi dengan pesannya', function (string $note, string $message) {
    $this->report->forceFill(['status' => ReportStatus::Submitted, 'submitted_at' => now()])->save();
    $this->actingAs($this->reviewer);

    // Dicek lewat halaman hasil redirect (assertSessionHasErrors menghabiskan flash untuk permintaan berikutnya).
    $this->from("/opd/laporan/{$this->report->id}")
        ->followingRedirects()
        ->post("/opd/laporan/{$this->report->id}/minta-revisi", ['revision_note' => $note])
        ->assertOk()
        ->assertSee($message);

    expect($this->report->fresh()->status)->toBe(ReportStatus::Submitted);
})->with([
    ['', 'Isi catatan revisi agar kelompok tahu apa yang perlu diperbaiki.'],
    ['Kurang.', 'Catatan revisi terlalu singkat. Tulis paling sedikit 10 karakter.'],
]);

test('laporan yang sudah ditinjau tidak bisa ditinjau lagi; peninjau mendapat penjelasan', function () {
    $this->report->forceFill(['status' => ReportStatus::Submitted, 'submitted_at' => now()])->save();
    $this->actingAs($this->reviewer)->post("/opd/laporan/{$this->report->id}/verifikasi");

    $this->actingAs(User::factory()->opd($this->opd)->create())
        ->post("/opd/laporan/{$this->report->id}/minta-revisi", ['revision_note' => 'Catatan yang terlambat.'])
        ->assertRedirect("/opd/laporan/{$this->report->id}")
        ->assertSessionHas('error', 'Laporan ini sudah ditinjau oleh Petugas Pertanian. Status sekarang: Terverifikasi.');

    expect($this->report->reviews()->count())->toBe(1);
});

test('OPD lain dan draf yang belum diajukan: 404 untuk aksi tinjau', function () {
    $this->actingAs($this->reviewer)->post("/opd/laporan/{$this->report->id}/verifikasi")->assertNotFound();

    $this->report->forceFill(['status' => ReportStatus::Submitted, 'submitted_at' => now()])->save();
    $this->actingAs(User::factory()->opd(Opd::factory()->create())->create())
        ->post("/opd/laporan/{$this->report->id}/verifikasi")->assertNotFound();

    expect($this->report->fresh()->status)->toBe(ReportStatus::Submitted);
});

test('kelompok tidak bisa memakai aksi peninjau', function () {
    $this->report->forceFill(['status' => ReportStatus::Submitted, 'submitted_at' => now()])->save();
    $this->actingAs($this->member);

    $this->post("/opd/laporan/{$this->report->id}/verifikasi")->assertForbidden();
    $this->post("/admin/laporan/{$this->report->id}/buka-kembali", ['reopen_note' => 'Alasan yang cukup panjang.'])->assertForbidden();
    expect($this->report->fresh()->status)->toBe(ReportStatus::Submitted);
});

test('admin juga bisa memverifikasi dan meminta revisi', function () {
    $this->report->forceFill(['status' => ReportStatus::Submitted, 'submitted_at' => now()])->save();

    $this->actingAs($this->admin)->get("/admin/laporan/{$this->report->id}")->assertSee('Verifikasi');
    $this->post("/admin/laporan/{$this->report->id}/verifikasi", ['verify_note' => 'Sudah dicek.'])
        ->assertRedirect("/admin/laporan/{$this->report->id}");

    expect($this->report->fresh()->status)->toBe(ReportStatus::Verified)
        ->and($this->report->reviews()->sole()->note)->toBe('Sudah dicek.');
});

test('admin membuka kembali laporan terverifikasi dengan alasan; kelompok melihat alasannya', function () {
    $this->report->forceFill(['status' => ReportStatus::Verified, 'submitted_at' => now(), 'verified_at' => now(), 'verified_by' => $this->reviewer->id])->save();
    $attachment = Attachment::factory()->create(['attachable_type' => 'monthly_report', 'attachable_id' => $this->report->id]);

    $this->actingAs($this->reviewer)->get("/opd/laporan/{$this->report->id}")->assertDontSee('Buka kembali');
    $this->post("/opd/laporan/{$this->report->id}/buka-kembali", ['reopen_note' => 'Alasan yang cukup panjang.'])->assertNotFound();

    $this->actingAs($this->admin)->get("/admin/laporan/{$this->report->id}")->assertSee('Buka kembali untuk revisi');
    $this->post("/admin/laporan/{$this->report->id}/buka-kembali", ['reopen_note' => 'Pendek'])
        ->assertSessionHasErrors(['reopen_note' => 'Alasan terlalu singkat. Tulis paling sedikit 10 karakter.']);
    expect($this->report->fresh()->status)->toBe(ReportStatus::Verified);
    $this->delete("/lampiran/{$attachment->id}")->assertForbidden();

    $this->post("/admin/laporan/{$this->report->id}/buka-kembali", ['reopen_note' => 'Angka penjualan salah ketik, mohon diperbaiki.'])
        ->assertSessionHas('status', 'Laporan dibuka kembali untuk revisi.');

    $report = $this->report->fresh();
    expect($report->status)->toBe(ReportStatus::Revision)
        ->and($report->verified_at)->toBeNull()
        ->and($report->reviews()->latest('id')->first()->action)->toBe(ReviewAction::Reopened);

    $this->actingAs($this->member)->get("/kelompok/laporan/{$this->report->id}/ubah")
        ->assertOk()->assertSee('Angka penjualan salah ketik, mohon diperbaiki.');
    $this->get("/kelompok/laporan/{$this->report->id}")->assertSee('Dibuka kembali untuk revisi');
});

test('membuka kembali laporan yang belum terverifikasi ditolak dengan penjelasan', function () {
    $this->report->forceFill(['status' => ReportStatus::Submitted, 'submitted_at' => now()])->save();

    $this->actingAs($this->admin)
        ->post("/admin/laporan/{$this->report->id}/buka-kembali", ['reopen_note' => 'Alasan yang cukup panjang.'])
        ->assertSessionHas('error');

    expect($this->report->fresh()->status)->toBe(ReportStatus::Submitted);
});
