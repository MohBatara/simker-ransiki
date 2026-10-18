<?php

use App\Enums\IssueKind;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->otherOpd = Opd::factory()->create();
    $this->ours = Organization::factory()->create(['name' => 'KUB Binaan']);
    $this->ours->opds()->attach($this->opd);
    $this->foreign = Organization::factory()->create(['name' => 'KUB Lain']);
    $this->foreign->opds()->attach($this->otherOpd);
});

function issueOn(Organization $organization, ReportStatus $status, string $period, IssueKind $kind, string $category, string $description): MonthlyReport
{
    $report = MonthlyReport::query()->firstOrCreate(
        ['organization_id' => $organization->id, 'period' => $period],
        ['status' => $status, 'submitted_at' => $status === ReportStatus::Draft ? null : now()],
    );
    $report->issues()->create(['kind' => $kind, 'category' => $category, 'description' => $description]);

    return $report;
}

test('OPD hanya melihat masalah dan kebutuhan dari laporan binaan yang diajukan atau terverifikasi', function () {
    $submitted = issueOn($this->ours, ReportStatus::Submitted, '2026-09-01', IssueKind::Problem, 'modal', 'Modal kerja terbatas');
    issueOn($this->ours, ReportStatus::Verified, '2026-08-01', IssueKind::Support, 'pelatihan', 'Pelatihan pengemasan');
    issueOn($this->ours, ReportStatus::Draft, '2026-07-01', IssueKind::Problem, 'peralatan', 'Isi draf rahasia');
    issueOn($this->ours, ReportStatus::Revision, '2026-06-01', IssueKind::Problem, 'sdm', 'Isi revisi rahasia');
    issueOn($this->foreign, ReportStatus::Verified, '2026-09-01', IssueKind::Problem, 'modal', 'Masalah kelompok lain');

    $this->actingAs(User::factory()->opd($this->opd)->create())
        ->get('/opd/laporan/masalah')
        ->assertOk()
        ->assertSeeInOrder(['Modal kerja terbatas', 'Pelatihan pengemasan'])
        ->assertSee('Bahan baku')
        ->assertSee("/opd/laporan/masalah/{$submitted->issues->first()->id}")
        ->assertDontSee('Isi draf rahasia')
        ->assertDontSee('Isi revisi rahasia')
        ->assertDontSee('Masalah kelompok lain')
        ->assertSee('<title>Masalah &amp; kebutuhan · Laporan', false);
});

test('filter jenis, kategori, dan periode', function () {
    issueOn($this->ours, ReportStatus::Submitted, '2026-09-01', IssueKind::Problem, 'modal', 'Modal kerja terbatas');
    issueOn($this->ours, ReportStatus::Submitted, '2026-09-01', IssueKind::Support, 'modal', 'Butuh pinjaman modal');
    issueOn($this->ours, ReportStatus::Verified, '2026-08-01', IssueKind::Problem, 'pemasaran', 'Pasar jauh');
    $this->actingAs(User::factory()->opd($this->opd)->create());

    $descriptions = fn (string $query) => $this->get('/opd/laporan/masalah?'.$query)->viewData('issues')->pluck('description')->all();

    expect($descriptions('kind=support'))->toBe(['Butuh pinjaman modal'])
        ->and($descriptions('category=modal'))->toEqualCanonicalizing(['Modal kerja terbatas', 'Butuh pinjaman modal'])
        ->and($descriptions('kind=problem&category=modal'))->toBe(['Modal kerja terbatas'])
        ->and($descriptions('period=2026-08'))->toBe(['Pasar jauh']);

    // Pilihan kategori mengikuti jenis yang dipilih.
    $this->get('/opd/laporan/masalah?kind=support')->assertSee('Bantuan alat')->assertDontSee('Bahan baku');
});

test('admin melihat seluruh distrik dan bisa menyaring per OPD', function () {
    issueOn($this->ours, ReportStatus::Submitted, '2026-09-01', IssueKind::Problem, 'modal', 'Modal kerja terbatas');
    $foreign = issueOn($this->foreign, ReportStatus::Verified, '2026-09-01', IssueKind::Problem, 'modal', 'Masalah kelompok lain');
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/laporan/masalah')->assertOk()->assertSee('Modal kerja terbatas')->assertSee('Masalah kelompok lain')
        ->assertSee("/admin/laporan/masalah/{$foreign->issues->first()->id}")
        ->assertSee('<title>Masalah &amp; kebutuhan · Laporan', false);
    expect($this->get("/admin/laporan/masalah?opd={$this->otherOpd->id}")->viewData('issues')->pluck('description')->all())->toBe(['Masalah kelompok lain']);
});

test('keadaan kosong dijelaskan', function () {
    $this->actingAs(User::factory()->opd($this->opd)->create())->get('/opd/laporan/masalah')->assertOk()->assertSee('Belum ada masalah atau kebutuhan');
});

test('peran lain ditolak 403', function () {
    $this->actingAs(User::factory()->kelompok($this->ours)->create());
    $this->get('/opd/laporan/masalah')->assertForbidden();
    $this->get('/admin/laporan/masalah')->assertForbidden();

    $this->actingAs(User::factory()->opd($this->opd)->create())->get('/admin/laporan/masalah')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/opd/laporan/masalah')->assertForbidden();
});
