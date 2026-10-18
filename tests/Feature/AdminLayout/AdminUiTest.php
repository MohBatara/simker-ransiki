<?php

use App\Enums\FollowUpStatus;
use App\Enums\LicenseStatus;
use App\Enums\OrganizationStatus;
use App\Enums\ReportingState;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Support\StatusTone;

test('satu peta warna status untuk seluruh aplikasi', function () {
    expect(StatusTone::of(ReportStatus::Verified))->toBe('success')
        ->and(StatusTone::of(OrganizationStatus::Active))->toBe('success')
        ->and(StatusTone::of(LicenseStatus::Issued))->toBe('success')
        ->and(StatusTone::of(ReportStatus::Submitted))->toBe('warning')
        ->and(StatusTone::of(LicenseStatus::InProcess))->toBe('warning')
        ->and(StatusTone::of(ReportStatus::Revision))->toBe('orange')
        ->and(StatusTone::of(LicenseStatus::NeedsFix))->toBe('orange')
        ->and(StatusTone::of(ReportingState::Overdue))->toBe('danger')
        ->and(StatusTone::of(OrganizationStatus::Closed))->toBe('danger')
        ->and(StatusTone::of(FollowUpStatus::Unable))->toBe('danger')
        ->and(StatusTone::of(ReportStatus::Draft))->toBe('neutral')
        ->and(StatusTone::of(OrganizationStatus::Inactive))->toBe('neutral')
        ->and(StatusTone::of(LicenseStatus::None))->toBe('neutral');
});

test('pesan sistem tampil sebagai toast seragam, bukan alert', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->withSession(['status' => 'Kelompok disimpan.'])
        ->get('/admin/kelompok')
        ->assertOk()
        ->assertSee('x-data="toasts(', false)
        ->assertSee('data-flash="ok">Kelompok disimpan.</span>', false);
});

test('detail laporan: Verifikasi dan Minta revisi di bar bawah yang menempel, dialog konfirmasi seragam', function () {
    $opd = Opd::factory()->create();
    $organization = Organization::factory()->create();
    $organization->opds()->attach($opd);
    $report = MonthlyReport::factory()->for($organization)->status(ReportStatus::Submitted)->create(['submitted_at' => now()]);

    $html = $this->actingAs(User::factory()->opd($opd)->create())->get("/opd/laporan/{$report->id}")->assertOk()->getContent();
    preg_match('#<div class="sticky bottom-\[var\(--app-bottom,0px\)\].*?</div>\s*</div>#s', $html, $bar);

    expect($bar[0] ?? '')->toContain('Minta revisi')->toContain('Verifikasi')
        ->and($html)->toContain('id="form-verify"')->toContain('id="form-revision"');
});

test('aksi sekunder header sebaris di desktop dan masuk menu "…" di HP; aksi utama selalu terlihat', function () {
    $html = $this->actingAs(User::factory()->admin()->create())->get('/admin/kelompok')->assertOk()->getContent();
    preg_match('#<header class="flex flex-col gap-4 md:flex-row.*?</header>#s', $html, $header);

    expect($header[0])->toContain('aria-label="Menu tindakan"')
        ->and(substr_count($header[0], 'Unduh Excel'))->toBe(2)
        ->and(substr_count($header[0], 'Tambah kelompok'))->toBe(1);
});
