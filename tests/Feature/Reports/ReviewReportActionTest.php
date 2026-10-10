<?php

use App\Actions\Reports\ReviewReport;
use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ReportReviewed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    $this->opd = Opd::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->opds()->attach($this->opd);
    $this->reviewer = User::factory()->opd($this->opd)->create(['name' => 'Petugas Satu']);
    $this->member = User::factory()->kelompok($this->organization)->create();
    $this->inactiveMember = User::factory()->kelompok($this->organization)->create(['is_active' => false]);
    $this->report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Submitted)->create([
        'submitted_at' => '2026-10-05 09:00:00',
    ]);
    $this->review = app(ReviewReport::class);
});

test('verifikasi: status terverifikasi, peninjau tercatat, notifikasi ke akun kelompok aktif', function () {
    $this->review->verify($this->report, $this->reviewer, 'Data lengkap.');

    $report = $this->report->fresh();
    expect($report->status)->toBe(ReportStatus::Verified)
        ->and($report->verified_by)->toBe($this->reviewer->id)
        ->and($report->verified_at)->not->toBeNull()
        ->and($report->reviews()->sole()->only(['action', 'note', 'user_id']))->toBe(['action' => ReviewAction::Verified, 'note' => 'Data lengkap.', 'user_id' => $this->reviewer->id]);

    Notification::assertSentTo($this->member, ReportReviewed::class, fn ($notification) => $notification->toArray($this->member)['action'] === 'verified');
    Notification::assertNotSentTo([$this->inactiveMember, $this->reviewer], ReportReviewed::class);
});

test('minta revisi: status perlu revisi, catatan tercatat, waktu pengajuan pertama tetap', function () {
    $this->review->requestRevision($this->report, $this->reviewer, 'Lengkapi angka biaya bulan ini.');

    $report = $this->report->fresh()->load('latestRevisionRequest');
    expect($report->status)->toBe(ReportStatus::Revision)
        ->and($report->submitted_at->format('Y-m-d H:i'))->toBe('2026-10-05 09:00')
        ->and($report->verified_at)->toBeNull()
        ->and($report->latestRevisionRequest->note)->toBe('Lengkapi angka biaya bulan ini.');

    Notification::assertSentTo($this->member, ReportReviewed::class);
});

test('peninjau kedua mendapat pesan bahwa laporan sudah ditinjau, tanpa riwayat ganda', function () {
    $other = User::factory()->opd($this->opd)->create();
    $stale = $this->report->fresh();

    $this->review->verify($this->report, $this->reviewer, null);

    expect(fn () => $this->review->requestRevision($stale, $other, 'Catatan yang terlambat dikirim.'))
        ->toThrow(ValidationException::class, 'Laporan ini sudah ditinjau oleh Petugas Satu. Status sekarang: Terverifikasi.');

    expect($this->report->reviews()->count())->toBe(1)->and($this->report->fresh()->status)->toBe(ReportStatus::Verified);
});

test('admin membuka kembali laporan terverifikasi: verifikasi dicabut, alasan menjadi catatan revisi', function () {
    $admin = User::factory()->admin()->create();
    $this->review->verify($this->report, $this->reviewer, null);

    $this->review->reopen($this->report, $admin, 'Angka penjualan ternyata salah ketik.');

    $report = $this->report->fresh()->load('latestRevisionRequest');
    expect($report->status)->toBe(ReportStatus::Revision)
        ->and($report->verified_at)->toBeNull()
        ->and($report->verified_by)->toBeNull()
        ->and($report->latestRevisionRequest->action)->toBe(ReviewAction::Reopened)
        ->and($report->reviews()->orderBy('id')->pluck('action')->map->value->all())->toBe(['verified', 'reopened']);

    Notification::assertSentTo($this->member, ReportReviewed::class, fn ($notification) => $notification->toArray($this->member)['action'] === 'reopened');
});

test('OPD tidak bisa membuka kembali laporan terverifikasi', function () {
    $this->review->verify($this->report, $this->reviewer, null);

    expect(fn () => $this->review->reopen($this->report, $this->reviewer, 'Saya ingin membukanya lagi.'))->toThrow(AuthorizationException::class);
    expect($this->report->fresh()->status)->toBe(ReportStatus::Verified);
});
