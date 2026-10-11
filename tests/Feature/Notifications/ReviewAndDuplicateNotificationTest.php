<?php

use App\Enums\ReportStatus;
use App\Models\BusinessSector;
use App\Models\DuplicateDismissal;
use App\Models\InstitutionType;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Models\Village;
use App\Notifications\PossibleDuplicate;
use App\Notifications\ReportReviewed;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jayapura'));

    $this->opd = Opd::factory()->create(['short_name' => 'Dinkopdag']);
    $this->village = Village::factory()->create(['name' => 'Kampung Contoh 1']);
    $this->organization = Organization::factory()->create(['name' => 'KUB Tanpa HP', 'village_id' => $this->village->id, 'reporting_starts_on' => '2026-07-01']);
    $this->organization->opds()->attach($this->opd);
    $this->officer = User::factory()->opd($this->opd)->create(['name' => 'Petugas Pengisi']);
    $this->admin = User::factory()->admin()->create(['name' => 'Admin Satu']);
});

afterEach(fn () => Carbon::setTestNow());

test('admin meminta revisi laporan yang diisi OPD: OPD pengisi mendapat notifikasi dan bisa membukanya', function () {
    $this->actingAs($this->officer)->post("/opd/kelompok/{$this->organization->id}/laporan", ['period' => '2026-09']);
    $report = $this->organization->monthlyReports()->firstOrFail();
    $this->patchJson("/opd/laporan/{$report->id}/draf", ['activity_summary' => 'Panen sagu', 'members_active' => '8'])->assertOk();
    $this->post("/opd/laporan/{$report->id}/ajukan");

    $this->actingAs($this->admin)->post("/admin/laporan/{$report->id}/minta-revisi", ['revision_note' => 'Lengkapi angka biaya.'])
        ->assertSessionHas('status');

    expect($this->officer->notifications()->where('type', ReportReviewed::class)->count())->toBe(1)
        ->and($this->admin->notifications()->where('type', ReportReviewed::class)->count())->toBe(0);

    $this->actingAs($this->officer)->get('/notifikasi')
        ->assertSee('Laporan September 2026 KUB Tanpa HP diminta revisi oleh Admin Satu (Admin distrik).')
        ->assertSee('Lengkapi angka biaya.');

    $notification = $this->officer->notifications()->where('type', ReportReviewed::class)->sole();
    $this->get("/notifikasi/{$notification->id}")->assertRedirect("/opd/laporan/{$report->id}");
    $this->get("/opd/laporan/{$report->id}")->assertSee('Perbaiki laporan');
});

test('peninjauan OPD juga diberitahukan ke kelompok dan petugas OPD pembina lain, bukan ke peninjaunya', function () {
    $colleague = User::factory()->opd($this->opd)->create();
    $member = User::factory()->kelompok($this->organization)->create();
    $otherOpdUser = User::factory()->opd(Opd::factory()->create())->create();
    $report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Submitted)
        ->create(['period' => '2026-09-01', 'submitted_at' => now(), 'entered_by' => $member->id]);

    $this->actingAs($this->officer)->post("/opd/laporan/{$report->id}/verifikasi", ['verify_note' => '']);

    $count = fn (User $user) => $user->notifications()->where('type', ReportReviewed::class)->count();
    expect($count($member))->toBe(1)
        ->and($count($colleague))->toBe(1)
        ->and($count($this->officer))->toBe(0)
        ->and($count($otherOpdUser))->toBe(0)
        ->and($count($this->admin))->toBe(0);

    $this->actingAs($member)->get('/notifikasi')->assertSee('Laporan September 2026 sudah diverifikasi.');
    $this->actingAs($colleague)->get('/notifikasi')->assertSee('Laporan September 2026 KUB Tanpa HP diverifikasi oleh Petugas Pengisi (Dinkopdag).');
});

test('kemungkinan data ganda saat menambah kelompok: semua admin aktif mendapat notifikasi yang menautkan ke halaman data ganda', function () {
    $secondAdmin = User::factory()->admin()->create();
    $inactiveAdmin = User::factory()->admin()->create(['is_active' => false]);
    Organization::factory()->create(['name' => 'Kelompok Tani Maju', 'village_id' => Village::factory()->create()->id]);

    $this->actingAs($this->admin)->post('/admin/kelompok', [
        'name' => 'Kel. Tani Maju',
        'institution_type_id' => InstitutionType::factory()->create()->id,
        'village_id' => $this->village->id,
        'sectors' => [BusinessSector::factory()->create()->id],
        'status' => 'active',
        'legal_status' => 'unknown',
        'reporting_month' => 9,
        'reporting_year' => 2026,
    ])->assertSessionHasNoErrors();

    foreach ([$this->admin, $secondAdmin] as $admin) {
        expect($admin->notifications()->where('type', PossibleDuplicate::class)->count())->toBe(1);
    }
    expect($inactiveAdmin->notifications()->count())->toBe(0)
        ->and($this->officer->notifications()->count())->toBe(0);

    $this->get('/notifikasi')->assertSee('Kemungkinan data ganda: Kel. Tani Maju mirip dengan Kelompok Tani Maju di kampung lain (ditambahkan oleh Admin Satu).');
    $notification = $this->admin->notifications()->sole();
    $this->get("/notifikasi/{$notification->id}")->assertRedirect('/admin/kelompok/data-ganda');
});

test('pasangan yang sudah ditandai bukan data ganda tidak memicu notifikasi', function () {
    $other = Organization::factory()->create(['name' => 'KUB Kakao Jaya', 'village_id' => $this->village->id]);
    $organization = Organization::factory()->create(['name' => 'KUB Lama', 'village_id' => $this->village->id]);
    DuplicateDismissal::dismiss($other->id, $organization->id, $this->admin);

    $this->actingAs($this->admin)->put("/admin/kelompok/{$organization->id}", [
        'name' => 'KUB Kakao Jaya',
        'institution_type_id' => $organization->institution_type_id,
        'village_id' => $this->village->id,
        'sectors' => [BusinessSector::factory()->create()->id],
        'legal_status' => 'unknown',
        'reporting_month' => 9,
        'reporting_year' => 2026,
    ])->assertRedirect("/admin/kelompok/{$organization->id}");

    expect($this->admin->notifications()->count())->toBe(0);
});
