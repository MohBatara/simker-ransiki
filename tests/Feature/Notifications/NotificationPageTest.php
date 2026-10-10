<?php

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ReportReviewed;
use App\Notifications\ReportSubmitted;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->organization = Organization::factory()->create(['name' => 'KUB Contoh 01']);
    $this->organization->opds()->attach($this->opd);
    $this->member = User::factory()->kelompok($this->organization)->create();
    $this->reviewer = User::factory()->opd($this->opd)->create();
    $this->report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => now()]);
});

test('daftar hanya berisi notifikasi milik sendiri, terbaru di atas, dengan angka belum dibaca', function () {
    $this->reviewer->notify(new ReportSubmitted($this->report));
    Carbon::setTestNow(now()->addMinute());
    $newer = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Submitted)->create(['period' => '2026-08-01', 'submitted_at' => now()]);
    $this->reviewer->notify(new ReportSubmitted($newer, resubmitted: true));
    $this->member->notify(new ReportReviewed($this->report, ReviewAction::Verified, null));
    Carbon::setTestNow();

    $this->actingAs($this->reviewer)->get('/notifikasi')
        ->assertOk()
        ->assertSeeInOrder([
            'Laporan Agustus 2026 KUB Contoh 01 diajukan ulang dan menunggu verifikasi.',
            'Laporan September 2026 KUB Contoh 01 menunggu verifikasi.',
        ])
        ->assertSee('2 belum dibaca.')
        ->assertSee('aria-label="Notifikasi, 2 belum dibaca"', false)
        ->assertSee('Tandai semua dibaca')
        ->assertDontSee('sudah diverifikasi');
});

test('lebih dari 9 belum dibaca ditampilkan 9+', function () {
    foreach (range(1, 12) as $i) {
        $this->member->notify(new ReportReviewed($this->report, ReviewAction::Verified, null));
    }

    $this->actingAs($this->member)->get('/kelompok')
        ->assertSee('aria-label="Notifikasi, 12 belum dibaca"', false)
        ->assertSee('>9+</span>', false);
});

test('membuka notifikasi menandainya dibaca dan mengarah ke laporan sesuai peran', function () {
    $this->reviewer->notify(new ReportSubmitted($this->report));
    $notification = $this->reviewer->notifications()->sole();

    $this->actingAs($this->reviewer)->get("/notifikasi/{$notification->id}")->assertRedirect("/opd/laporan/{$this->report->id}");

    expect($notification->fresh()->read_at)->not->toBeNull();
    $this->get('/opd')->assertSee('aria-label="Notifikasi"', false)->assertDontSee('belum dibaca"', false);
});

test('kelompok diarahkan ke form bila laporan perlu revisi, ke detail bila sudah terverifikasi', function () {
    $this->report->forceFill(['status' => ReportStatus::Revision])->save();
    $this->member->notify(new ReportReviewed($this->report, ReviewAction::RevisionRequested, 'Lengkapi foto.'));
    $revision = $this->member->notifications()->sole();

    $this->actingAs($this->member)->get("/notifikasi/{$revision->id}")->assertRedirect("/kelompok/laporan/{$this->report->id}/ubah");

    $this->report->forceFill(['status' => ReportStatus::Verified])->save();
    $this->get("/notifikasi/{$revision->id}")->assertRedirect("/kelompok/laporan/{$this->report->id}");
});

test('notifikasi tidak memberi akses tambahan: akses dicabut, pengguna kembali ke daftar dengan pesan', function () {
    $this->reviewer->notify(new ReportSubmitted($this->report));
    $notification = $this->reviewer->notifications()->sole();
    $this->organization->opds()->detach();

    $this->actingAs($this->reviewer)->get("/notifikasi/{$notification->id}")
        ->assertRedirect('/notifikasi')
        ->assertSessionHas('error', 'Laporan ini tidak bisa dibuka lagi dari akun Anda.');
});

test('notifikasi milik orang lain: 404 dan tidak ikut ditandai', function () {
    $this->member->notify(new ReportReviewed($this->report, ReviewAction::Verified, null));
    $notification = $this->member->notifications()->sole();

    $this->actingAs($this->reviewer)->get("/notifikasi/{$notification->id}")->assertNotFound();
    $this->post('/notifikasi/tandai-semua')->assertRedirect('/notifikasi');

    expect($notification->fresh()->read_at)->toBeNull();
});

test('tandai semua dibaca hanya untuk milik sendiri', function () {
    $this->reviewer->notify(new ReportSubmitted($this->report));
    $this->member->notify(new ReportReviewed($this->report, ReviewAction::Verified, null));

    $this->actingAs($this->reviewer)->post('/notifikasi/tandai-semua')->assertSessionHas('status', 'Semua notifikasi ditandai dibaca.');

    expect($this->reviewer->unreadNotifications()->count())->toBe(0)
        ->and($this->member->unreadNotifications()->count())->toBe(1);
    $this->get('/notifikasi')->assertSee('Semua sudah dibaca.')->assertDontSee('Tandai semua dibaca');
});

test('keadaan kosong dan halaman untuk semua peran', function () {
    foreach ([$this->member, $this->reviewer, User::factory()->admin()->create()] as $user) {
        $this->actingAs($user)->get('/notifikasi')->assertOk()->assertSee('Belum ada notifikasi');
    }

    auth()->logout();
    $this->get('/notifikasi')->assertRedirect('/login');
});
