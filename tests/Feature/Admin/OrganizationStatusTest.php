<?php

use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Models\BusinessSector;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\OrganizationStatusChange;
use App\Models\User;
use App\Support\Dashboards\ReportingStats;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 09:00', 'Asia/Jayapura'));
    $this->admin = User::factory()->admin()->create(['name' => 'Admin Distrik']);
    $this->opd = Opd::factory()->create();
    $this->organization = Organization::factory()->create(['name' => 'KUB Contoh Status', 'reporting_starts_on' => '2026-01-01']);
    $this->organization->opds()->attach($this->opd);
    $this->opdUser = User::factory()->opd($this->opd)->create();
    $this->member = User::factory()->kelompok($this->organization)->create();
    $this->url = "/admin/kelompok/{$this->organization->id}/status";
});

test('admin mengubah status dengan alasan dan riwayatnya tersimpan', function () {
    $this->actingAs($this->admin)
        ->patch($this->url, ['status' => 'inactive', 'reason' => 'Pengurus sedang panen raya.'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Status kelompok diubah menjadi tidak aktif sementara.');

    $this->patch($this->url, ['status' => 'closed', 'reason' => 'Kelompok bubar sejak Agustus.']);

    expect($this->organization->fresh()->status)->toBe(OrganizationStatus::Closed);

    $changes = $this->organization->statusChanges()->get();
    expect($changes)->toHaveCount(2)
        ->and($changes[0]->from_status)->toBe(OrganizationStatus::Inactive)
        ->and($changes[0]->to_status)->toBe(OrganizationStatus::Closed)
        ->and($changes[0]->reason)->toBe('Kelompok bubar sejak Agustus.')
        ->and($changes[0]->user_id)->toBe($this->admin->id)
        ->and($changes[0]->created_at)->not->toBeNull()
        ->and($changes[1]->from_status)->toBe(OrganizationStatus::Active);

    $this->get("/admin/kelompok/{$this->organization->id}")
        ->assertOk()
        ->assertSee('Riwayat status')
        ->assertSee('Tidak aktif sementara → Ditutup')
        ->assertSee('Kelompok bubar sejak Agustus.')
        ->assertSee('Admin Distrik');
});

test('alasan wajib dan status harus berbeda dari status sekarang', function () {
    $this->actingAs($this->admin);

    $this->patch($this->url, ['status' => 'closed'])->assertSessionHasErrors(['reason' => 'Tulis alasan singkat perubahan status.']);
    $this->patch($this->url, ['status' => 'closed', 'reason' => 'ok'])->assertSessionHasErrors('reason');
    $this->patch($this->url, ['status' => 'active', 'reason' => 'Tetap aktif saja.'])
        ->assertSessionHasErrors(['status' => 'Kelompok sudah berstatus aktif. Pilih status lain.']);
    $this->patch($this->url, ['status' => 'bubar', 'reason' => 'Status tidak dikenal.'])->assertSessionHasErrors('status');

    expect(OrganizationStatusChange::count())->toBe(0)
        ->and($this->organization->fresh()->status)->toBe(OrganizationStatus::Active);
});

test('modal ubah status terbuka lagi bila validasi gagal', function () {
    $this->actingAs($this->admin)
        ->from("/admin/kelompok/{$this->organization->id}")
        ->followingRedirects()
        ->patch($this->url, ['status' => 'closed'])
        ->assertSee('Tulis alasan singkat perubahan status.')
        ->assertSee('x-init="$el.showModal()"', false);
});

test('hanya admin yang bisa mengubah status', function () {
    $this->actingAs($this->opdUser)->patch($this->url, ['status' => 'closed', 'reason' => 'Dicoba oleh OPD.'])->assertForbidden();
    $this->actingAs($this->member)->patch($this->url, ['status' => 'closed', 'reason' => 'Dicoba oleh kelompok.'])->assertForbidden();
    $this->post('/logout');
    $this->patch($this->url, ['status' => 'closed', 'reason' => 'Dicoba oleh tamu.'])->assertRedirect('/login');

    expect($this->organization->fresh()->status)->toBe(OrganizationStatus::Active)
        ->and(OrganizationStatusChange::count())->toBe(0);
});

test('menu ubah status hanya tampil untuk admin', function () {
    $this->actingAs($this->admin)->get("/admin/kelompok/{$this->organization->id}")
        ->assertSee('Ubah status kelompok')->assertSee('Status baru');

    $this->actingAs($this->opdUser)->get("/opd/kelompok/{$this->organization->id}")
        ->assertOk()->assertDontSee('Ubah status kelompok')->assertDontSee('Riwayat status');
});

test('form ubah profil tidak lagi mengubah status kelompok yang sudah ada', function () {
    $organization = $this->organization;
    $payload = [
        'name' => $organization->name, 'institution_type_id' => $organization->institution_type_id,
        'village_id' => $organization->village_id, 'sectors' => [BusinessSector::factory()->create()->id], 'opds' => [$this->opd->id],
        'status' => 'closed', 'legal_status' => 'unknown', 'reporting_month' => 1, 'reporting_year' => 2026,
    ];

    $this->actingAs($this->admin)->put("/admin/kelompok/{$organization->id}", $payload)->assertSessionHasNoErrors();

    expect($organization->fresh()->status)->toBe(OrganizationStatus::Active);
    $this->get("/admin/kelompok/{$organization->id}/ubah")->assertSee('Ubah lewat tombol … di halaman detail kelompok');
});

test('kelompok ditutup: tidak bisa membuat laporan baru, tidak dihitung belum melapor, data tetap terlihat', function () {
    $report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Verified)->create(['period' => '2026-08-01', 'submitted_at' => '2026-09-05 10:00']);
    $this->organization->update(['status' => OrganizationStatus::Closed]);

    $this->actingAs($this->member)->post('/kelompok/laporan', ['period' => '2026-09'])->assertForbidden();
    expect($this->organization->monthlyReports()->count())->toBe(1);

    $september = CarbonImmutable::parse('2026-09-01');
    expect((new ReportingStats($this->admin, $september))->progress()['missing'])->toBe(0)
        ->and((new ReportingStats($this->admin, $september))->overdue()['total'])->toBe(0)
        ->and(Organization::obligatedFor($september)->count())->toBe(0);

    $this->actingAs($this->admin)->get("/admin/kelompok/{$this->organization->id}")->assertOk()->assertSee('Ditutup');
    $this->actingAs($this->admin)->get("/admin/laporan/{$report->id}")->assertOk();
    $this->actingAs($this->opdUser)->get("/opd/kelompok/{$this->organization->id}/laporan")->assertOk()->assertSee('Agustus 2026');
    $this->actingAs($this->admin)->get('/admin/kelompok?status=closed')->assertSee('KUB Contoh Status');
});

test('kelompok tidak aktif sementara tetap bisa masuk dan tetap wajib lapor', function () {
    $this->organization->update(['status' => OrganizationStatus::Inactive]);

    $this->post('/login', ['login' => $this->member->username, 'password' => 'password'])->assertRedirect('/kelompok');
    expect(Organization::obligatedFor(CarbonImmutable::parse('2026-09-01'))->count())->toBe(1);
});

test('filter status di daftar admin dan OPD', function () {
    Organization::factory()->closed()->create(['name' => 'KUB Sudah Tutup'])->opds()->attach($this->opd);

    $this->actingAs($this->admin)->get('/admin/kelompok?status=closed')
        ->assertSee('KUB Sudah Tutup')->assertDontSee('KUB Contoh Status');
    $this->actingAs($this->opdUser)->get('/opd/kelompok?status=active')
        ->assertSee('KUB Contoh Status')->assertDontSee('KUB Sudah Tutup');
});
