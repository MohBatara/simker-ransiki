<?php

use App\Actions\Reports\ReviewReport;
use App\Actions\Reports\SubmitReport;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ReportReviewed;
use App\Notifications\ReportSubmitted;
use App\Support\NotificationText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00', 'Asia/Jayapura'));
    $this->opd = Opd::factory()->create();
    $this->organization = Organization::factory()->create(['name' => 'KUB Contoh 01', 'reporting_starts_on' => '2026-07-01']);
    $this->organization->opds()->attach($this->opd);
    $this->member = User::factory()->kelompok($this->organization)->create();
    $this->report = MonthlyReport::factory()->for($this->organization)->create([
        'period' => '2026-09-01', 'activity_summary' => 'Panen', 'members_active' => 4,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function lastText(User $user): array
{
    // Waktu tes dibekukan; dimajukan agar urutan "terbaru" tidak bergantung pada id acak.
    Carbon::setTestNow(now()->addMinute());

    return NotificationText::describe($user->notifications()->latest()->firstOrFail());
}

test('teks notifikasi per jenis', function () {
    $reviewer = User::factory()->opd($this->opd)->create();
    $admin = User::factory()->admin()->create();
    $review = app(ReviewReport::class);

    app(SubmitReport::class)->handle($this->report, $this->member);
    expect(lastText($reviewer)['text'])->toBe('Laporan September 2026 KUB Contoh 01 menunggu verifikasi.');

    Carbon::setTestNow(now()->addMinute());
    $review->requestRevision($this->report, $reviewer, 'Lengkapi foto kegiatan.');
    expect(lastText($this->member))->toBe(['text' => 'Laporan September 2026 perlu revisi.', 'note' => 'Lengkapi foto kegiatan.']);

    Carbon::setTestNow(now()->addMinute());
    app(SubmitReport::class)->handle($this->report, $this->member);
    expect(lastText($reviewer)['text'])->toBe('Laporan September 2026 KUB Contoh 01 diajukan ulang dan menunggu verifikasi.');

    Carbon::setTestNow(now()->addMinute());
    $review->verify($this->report, $reviewer, null);
    expect(lastText($this->member))->toBe(['text' => 'Laporan September 2026 sudah diverifikasi.', 'note' => null]);

    Carbon::setTestNow(now()->addMinute());
    $review->reopen($this->report, $admin, 'Angka penjualan salah ketik.');
    expect(lastText($this->member))->toBe(['text' => 'Laporan September 2026 dibuka kembali untuk revisi oleh Admin distrik.', 'note' => 'Angka penjualan salah ketik.']);
});

test('notifikasi lama yang hanya menyimpan pesan tetap tampil', function () {
    $this->member->notifications()->create([
        'id' => (string) Str::uuid(), 'type' => ReportReviewed::class,
        'data' => ['message' => 'Laporan Agustus 2026 diverifikasi.', 'url' => '/kelompok/laporan/1'],
    ]);

    expect(lastText($this->member))->toBe(['text' => 'Laporan Agustus 2026 diverifikasi.', 'note' => null]);
});

test('kelompok tanpa OPD pembina: notifikasi pengajuan dikirim ke semua admin aktif', function () {
    $this->organization->opds()->detach();
    $admin = User::factory()->admin()->create();
    $inactiveAdmin = User::factory()->admin()->create(['is_active' => false]);
    $opdUser = User::factory()->opd($this->opd)->create();

    app(SubmitReport::class)->handle($this->report, $this->member);

    expect($admin->notifications()->count())->toBe(1)
        ->and($inactiveAdmin->notifications()->count())->toBe(0)
        ->and($opdUser->notifications()->count())->toBe(0)
        ->and(lastText($admin)['text'])->toBe('Laporan September 2026 KUB Contoh 01 menunggu verifikasi.');
});

test('kelompok dengan OPD pembina: admin tidak menerima notifikasi pengajuan', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->opd($this->opd)->create();

    app(SubmitReport::class)->handle($this->report, $this->member);

    expect($admin->notifications()->count())->toBe(0);
});

test('setelah ditinjau, notifikasi "menunggu verifikasi" ditandai dibaca bagi semua peninjau', function (bool $withOpd) {
    if (! $withOpd) {
        $this->organization->opds()->detach();
    }
    $first = $withOpd ? User::factory()->opd($this->opd)->create() : User::factory()->admin()->create();
    $second = $withOpd ? User::factory()->opd($this->opd)->create() : User::factory()->admin()->create();
    $other = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-08-01', 'activity_summary' => 'x', 'members_active' => 1]);

    app(SubmitReport::class)->handle($this->report, $this->member);
    app(SubmitReport::class)->handle($other, $this->member);
    app(ReviewReport::class)->verify($this->report, $first, null);

    $unread = fn (User $user) => $user->unreadNotifications()->where('type', ReportSubmitted::class)->pluck('data')->pluck('monthly_report_id')->all();
    expect($unread($first))->toBe([$other->id])->and($unread($second))->toBe([$other->id]);
})->with(['OPD pembina' => true, 'admin (tanpa OPD)' => false]);
