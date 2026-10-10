<?php

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Organization;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

test('tenggat dibaca dari config: tanggal 10 bulan berikutnya pukul 23.59 WIT', function () {
    $deadline = MonthlyReport::deadlineFor(Carbon::parse('2026-09-01'));

    expect($deadline->format('Y-m-d H:i:s'))->toBe('2026-10-10 23:59:59')
        ->and($deadline->timezoneName)->toBe('Asia/Jayapura');

    config(['simker.reports.deadline_day' => 15]);
    expect(MonthlyReport::deadlineFor(Carbon::parse('2026-12-01'))->format('Y-m-d'))->toBe('2027-01-15');
});

test('terlambat hanya bila lewat tenggat dan belum pernah diajukan', function () {
    $report = MonthlyReport::factory()->create(['period' => '2026-09-01']);

    Carbon::setTestNow(Carbon::parse('2026-10-10 23:59:00', 'Asia/Jayapura'));
    expect($report->isOverdue())->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-10-11 00:00:00', 'Asia/Jayapura'));
    expect($report->isOverdue())->toBeTrue();

    // Diajukan tepat waktu lalu diminta revisi: tidak terlambat.
    $report->forceFill(['status' => ReportStatus::Revision, 'submitted_at' => '2026-10-09 10:00:00'])->save();
    expect($report->fresh()->isOverdue())->toBeFalse();
});

test('label diajukan terlambat bila pengajuan pertama melewati tenggat', function () {
    $onTime = MonthlyReport::factory()->status(ReportStatus::Submitted)->create(['period' => '2026-08-01', 'submitted_at' => '2026-09-10 23:00:00']);
    $late = MonthlyReport::factory()->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => '2026-10-11 08:00:00']);
    $draft = MonthlyReport::factory()->create(['period' => '2026-07-01']);

    expect($onTime->wasSubmittedLate())->toBeFalse()
        ->and($late->wasSubmittedLate())->toBeTrue()
        ->and($draft->wasSubmittedLate())->toBeFalse();
});

test('laporan bulan berjalan baru bisa diajukan mulai tanggal 1 bulan berikutnya', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-31 23:00:00', 'Asia/Jayapura'));
    $report = MonthlyReport::factory()->create(['period' => '2026-10-01']);

    expect($report->submissionIsOpen())->toBeFalse()
        ->and($report->submissionOpensOn()->format('Y-m-d'))->toBe('2026-11-01');

    Carbon::setTestNow(Carbon::parse('2026-11-01 00:00:00', 'Asia/Jayapura'));
    expect($report->submissionIsOpen())->toBeTrue();
});

test('periode wajib lapor dari reporting_starts_on sampai bulan berjalan', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Jayapura'));
    $organization = Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);

    expect($organization->reportingPeriods()->map->format('Y-m')->all())->toBe(['2026-10', '2026-09', '2026-08', '2026-07'])
        ->and($organization->acceptsReportFor(Carbon::parse('2026-06-01')))->toBeFalse()
        ->and($organization->acceptsReportFor(Carbon::parse('2026-07-01')))->toBeTrue()
        ->and($organization->acceptsReportFor(Carbon::parse('2026-10-01')))->toBeTrue()
        ->and($organization->acceptsReportFor(Carbon::parse('2026-11-01')))->toBeFalse();
});
