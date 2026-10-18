<?php

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\ReportReview;
use App\Models\User;
use App\Support\Navigation;

/**
 * Satu definisi "menunggu verifikasi" (MonthlyReport::verificationQueue): badge menu Laporan, isi dan angka tab Perlu
 * diverifikasi, teks "N menunggu verifikasi" di Semua laporan, dan kartu dasbor harus selalu sama.
 */
beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->otherOpd = Opd::factory()->create();
    $this->ours = Organization::factory()->create();
    $this->ours->opds()->attach($this->opd);
    $this->foreign = Organization::factory()->create();
    $this->foreign->opds()->attach($this->otherOpd);
    $this->orphan = Organization::factory()->create();

    $this->admin = User::factory()->admin()->create();
    $this->opdUser = User::factory()->opd($this->opd)->create();
    User::factory()->opd($this->otherOpd)->create();

    $submitted = function (Organization $organization, string $period, int $daysAgo, ?User $enteredBy = null) {
        $report = MonthlyReport::factory()->for($organization)->status(ReportStatus::Submitted)
            ->create(['period' => $period, 'submitted_at' => now()->subDays($daysAgo), 'entered_by' => $enteredBy?->id]);
        ReportReview::forceCreate(['monthly_report_id' => $report->id, 'action' => ReviewAction::Submitted, 'user_id' => $enteredBy?->id, 'created_at' => now()->subDays($daysAgo)]);

        return $report;
    };

    $submitted($this->ours, '2026-07-01', 20);
    $submitted($this->ours, '2026-08-01', 2);
    // Diisi atas nama kelompok oleh petugas OPD ini sendiri: tetap dihitung, tetapi tidak bisa ia verifikasi.
    $this->handled = $submitted($this->ours, '2026-09-01', 9, $this->opdUser);
    $submitted($this->foreign, '2026-09-01', 1);
    $submitted($this->orphan, '2026-09-01', 30);
    MonthlyReport::factory()->for($this->ours)->status(ReportStatus::Draft)->create(['period' => '2026-06-01']);
    MonthlyReport::factory()->for($this->ours)->status(ReportStatus::Verified)->create(['period' => '2026-05-01', 'verified_at' => now()]);
});

test('badge, tab, teks Semua laporan, dan kartu dasbor memakai angka yang sama', function (string $role, int $expected, int $waitingLong) {
    $user = $role === 'admin' ? $this->admin : $this->opdUser;
    $area = $user->role->area();
    $this->actingAs($user);

    $badge = Navigation::counts($user)['reports'];
    $queue = $this->get("/{$area}/laporan/verifikasi")->assertOk();
    $all = $this->get("/{$area}/laporan")->assertOk();
    $dashboard = $this->get("/{$area}")->assertOk();
    $tabCount = fn (string $html) => preg_match('#Perlu diverifikasi\s*<span[^>]*>(\d+)</span>#', $html, $m) ? (int) $m[1] : null;

    expect($badge)->toBe($expected)
        ->and($queue->viewData('reports')->total())->toBe($expected)
        ->and($tabCount($queue->getContent()))->toBe($expected)
        ->and($tabCount($all->getContent()))->toBe($expected)
        ->and($all->viewData('submittedCount'))->toBe($expected)
        ->and(html_entity_decode(strip_tags($all->getContent())))->toContain("· {$expected} menunggu verifikasi")
        ->and($dashboard->getContent())->toContain('aria-label="Laporan, '.$expected.' menunggu verifikasi"');

    if ($role === 'admin') {
        expect($dashboard->viewData('attention')['waiting_total'])->toBe($expected)
            ->and($dashboard->viewData('attention')['waiting'])->toBe($waitingLong);
    } else {
        expect($dashboard->viewData('waitingCount'))->toBe($expected)
            ->and($dashboard->viewData('waitingLong'))->toBe($waitingLong);
    }
    // "Menunggu lebih dari 7 hari" = antrean yang sama dengan filter menunggu.
    expect($this->get("/{$area}/laporan/verifikasi?menunggu=7")->viewData('reports')->total())->toBe($waitingLong);
})->with([
    'admin' => ['admin', 5, 3],
    'opd' => ['opd', 3, 2],
]);

test('laporan yang diisi sendiri tetap di antrean dengan label, tombol Verifikasi nonaktif, dan server tetap menolak', function () {
    $this->actingAs($this->opdUser);

    $this->get('/opd/laporan/verifikasi')->assertSee('Diisi oleh Anda, perlu pemverifikasi lain');
    $html = $this->get("/opd/laporan/{$this->handled->id}")->assertOk()->getContent();

    expect($html)->toMatch('#<button[^>]*disabled[^>]*aria-describedby="alasan-verifikasi"#')
        ->not->toContain('id="form-verify"');
    $this->post("/opd/laporan/{$this->handled->id}/verifikasi")->assertForbidden();
});

test('Semua laporan: menunggu (terlama dulu), perlu revisi, terverifikasi (terbaru dulu), lalu draf redup tanpa tautan', function () {
    MonthlyReport::factory()->for($this->foreign)->status(ReportStatus::Revision)->create(['period' => '2026-08-01', 'submitted_at' => now()->subDays(40)]);
    MonthlyReport::factory()->for($this->foreign)->status(ReportStatus::Verified)->create(['period' => '2026-04-01', 'verified_at' => now()->subDays(3)]);
    $this->actingAs($this->admin);

    $response = $this->get('/admin/laporan')->assertOk();
    $reports = $response->viewData('reports');

    expect($reports->map(fn ($report) => $report->status->value)->all())->toBe([
        'submitted', 'submitted', 'submitted', 'submitted', 'submitted', 'revision', 'verified', 'verified', 'draft',
    ])
        // Menunggu: pengajuan terakhir paling lama dulu (30, 20, 9, 2, 1 hari).
        ->and($reports->take(5)->map(fn ($report) => (int) round($report->last_submitted_at->diffInDays(now())))->all())->toBe([30, 20, 9, 2, 1])
        // Terverifikasi: terbaru dulu.
        ->and($reports->slice(6, 2)->map(fn ($report) => $report->period->format('Y-m'))->values()->all())->toBe(['2026-05', '2026-04']);

    preg_match('#<tr[^>]*data-draft="true"[^>]*>#', $response->getContent(), $draftRow);
    expect($draftRow[0])->toContain('text-ink-soft')->not->toContain('data-row-href')
        ->and($response->getContent())->toContain('Draf, belum diajukan');

    // Filter status tetap bisa memilih Draf.
    expect($this->get('/admin/laporan?status=draft')->viewData('reports')->pluck('status')->map->value->all())->toBe(['draft']);
});
