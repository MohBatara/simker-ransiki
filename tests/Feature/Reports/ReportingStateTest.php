<?php

use App\Enums\OrganizationStatus;
use App\Enums\ReportingState;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Support\Dashboards\ReportingStats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->period = CarbonImmutable::parse('2026-09-01');
    $this->opd = Opd::factory()->create();
    $this->make = function (string $name, ?ReportStatus $status = null, array $attributes = []) {
        $organization = Organization::factory()->create(['name' => $name, 'reporting_starts_on' => '2026-07-01', ...$attributes]);
        $organization->opds()->attach($this->opd);

        if ($status) {
            MonthlyReport::factory()->for($organization)->status($status)->create([
                'period' => '2026-09-01',
                'submitted_at' => $status === ReportStatus::Draft ? null : '2026-10-05 10:00',
            ]);
        }

        return $organization;
    };
});

afterEach(fn () => Carbon::setTestNow());

test('batas zona waktu: 10 Oktober 23.59 WIT belum terlambat, 11 Oktober 00.01 WIT terlambat', function () {
    $draft = ($this->make)('Masih draf', ReportStatus::Draft);
    $missing = ($this->make)('Belum dibuat');
    $report = $draft->monthlyReports()->sole();

    // Server bisa berjalan di UTC: 14.59 UTC = 23.59 WIT.
    Carbon::setTestNow(Carbon::parse('2026-10-10 14:59:00', 'UTC'));
    expect(ReportingState::for($report, $this->period))->toBe(ReportingState::Draft)
        ->and(ReportingState::for(null, $this->period))->toBe(ReportingState::Missing)
        ->and($report->isOverdue())->toBeFalse()
        ->and(Organization::overdueFor($this->period)->count())->toBe(0);

    Carbon::setTestNow(Carbon::parse('2026-10-10 23:59:00', 'Asia/Jayapura'));
    expect(Organization::overdueFor($this->period)->count())->toBe(0);

    Carbon::setTestNow(Carbon::parse('2026-10-11 00:01:00', 'Asia/Jayapura'));
    expect(ReportingState::for($report, $this->period))->toBe(ReportingState::Overdue)
        ->and(ReportingState::for(null, $this->period))->toBe(ReportingState::Overdue)
        ->and($report->isOverdue())->toBeTrue()
        ->and(Organization::overdueFor($this->period)->pluck('name')->sort()->values()->all())->toBe(['Belum dibuat', 'Masih draf']);

    // 15.01 UTC = 00.01 WIT keesokan harinya.
    Carbon::setTestNow(Carbon::parse('2026-10-10 15:01:00', 'UTC'));
    expect(Organization::overdueFor($this->period)->count())->toBe(2);
});

test('status per kelompok: terverifikasi, menunggu, revisi tidak pernah terlambat; tutup dan belum mulai tidak dihitung', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 09:00', 'Asia/Jayapura'));

    ($this->make)('Terverifikasi', ReportStatus::Verified);
    ($this->make)('Menunggu', ReportStatus::Submitted);
    ($this->make)('Revisi', ReportStatus::Revision);
    ($this->make)('Draf', ReportStatus::Draft);
    ($this->make)('Belum dibuat');
    ($this->make)('Tidak aktif', null, ['status' => OrganizationStatus::Inactive]);
    ($this->make)('Tutup', null, ['status' => OrganizationStatus::Closed]);
    ($this->make)('Baru mulai', null, ['reporting_starts_on' => '2026-10-01']);

    $states = Organization::with('monthlyReports')->orderBy('name')->get()
        ->mapWithKeys(fn ($organization) => [$organization->name => ReportingState::for($organization->monthlyReports->first(), $this->period)->label()]);

    expect($states->only(['Terverifikasi', 'Menunggu', 'Revisi', 'Draf', 'Belum dibuat'])->all())->toBe([
        'Belum dibuat' => 'Terlambat', 'Draf' => 'Terlambat', 'Menunggu' => 'Menunggu verifikasi',
        'Revisi' => 'Perlu revisi', 'Terverifikasi' => 'Terverifikasi',
    ]);

    expect(Organization::overdueFor($this->period)->orderBy('name')->pluck('name')->all())->toBe(['Belum dibuat', 'Draf', 'Tidak aktif'])
        ->and((new ReportingStats(User::factory()->admin()->create(), $this->period))->overdue()['total'])->toBe(3);
});

test('dasbor Admin dan OPD: kartu terlambat dan daftar kelompok terlambat', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 09:00', 'Asia/Jayapura'));
    ($this->make)('KUB Lupa Lapor');
    ($this->make)('KUB Rajin', ReportStatus::Submitted);
    $otherOpd = Organization::factory()->create(['name' => 'KUB OPD Lain', 'reporting_starts_on' => '2026-07-01']);

    $this->actingAs(User::factory()->opd($this->opd)->create())->get('/opd')
        ->assertOk()
        ->assertSee('Terlambat melapor September 2026')
        ->assertSee('KUB Lupa Lapor')
        ->assertDontSee('KUB OPD Lain')
        ->assertSee('/opd/kelompok?laporan=terlambat&amp;periode=2026-09', false);

    $this->actingAs(User::factory()->admin()->create())->get('/admin')
        ->assertOk()
        ->assertSee('Terlambat melapor September 2026')
        ->assertSee('KUB OPD Lain');

    $this->get('/admin/kelompok?laporan=terlambat&periode=2026-09')
        ->assertSee('KUB Lupa Lapor')->assertSee('KUB OPD Lain')->assertDontSee('KUB Rajin')
        ->assertSee('Terlambat melapor September 2026');
});

test('sebelum tenggat daftar tetap "Belum mengajukan" tanpa badge terlambat', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 09:00', 'Asia/Jayapura'));
    ($this->make)('KUB Lupa Lapor');

    $this->actingAs(User::factory()->opd($this->opd)->create())->get('/opd')
        ->assertOk()
        ->assertSee('Belum mengajukan September 2026')
        ->assertSee('5 hari lagi')
        ->assertDontSee('>Terlambat</span>', false);
});

test('badge Terlambat di daftar laporan kelompok dan Admin/OPD', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 09:00', 'Asia/Jayapura'));
    $organization = ($this->make)('KUB Draf', ReportStatus::Draft);
    $member = User::factory()->kelompok($organization)->create();

    $badge = '>Terlambat</span>';
    $this->actingAs($member)->get('/kelompok/laporan')->assertOk()->assertSee($badge, false);
    $this->actingAs(User::factory()->opd($this->opd)->create())->get('/opd/laporan')->assertOk()->assertSee($badge, false);
    $this->get("/opd/kelompok/{$organization->id}/laporan")->assertOk()->assertSee($badge, false);
    $this->get('/opd')->assertSee($badge, false);
});

test('kelompok tutup tidak ditandai terlambat di daftar laporannya sendiri', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 09:00', 'Asia/Jayapura'));
    $organization = ($this->make)('KUB Tutup', null, ['status' => OrganizationStatus::Closed]);

    $this->actingAs(User::factory()->kelompok($organization)->create())->get('/kelompok/laporan')
        ->assertOk()->assertSee('Belum dibuat')->assertDontSee('>Terlambat</span>', false);
});

test('dasbor kelompok menampilkan sisa hari menuju tenggat', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-07 09:00', 'Asia/Jayapura'));
    $organization = ($this->make)('KUB Belum');

    $this->actingAs(User::factory()->kelompok($organization)->create())->get('/kelompok')
        ->assertOk()->assertSee('paling lambat 10 Oktober 2026 (3 hari lagi)');
});

test('teks tenggat: diajukan sebelum tenggat lalu revisi tidak terlambat; belum diajukan lewat tenggat terlambat N hari', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-14 09:00', 'Asia/Jayapura'));
    $period = Carbon::parse('2026-09-01');
    $revised = MonthlyReport::factory()->for(($this->make)('KUB Revisi'))->status(ReportStatus::Revision)->create(['period' => $period, 'submitted_at' => '2026-10-09 10:00']);
    $missing = ($this->make)('KUB Telat');

    expect(ReportingState::for($revised, $period))->toBe(ReportingState::Revision)
        ->and($revised->isOverdue())->toBeFalse()
        ->and(ReportingState::for(null, $period))->toBe(ReportingState::Overdue);

    $this->actingAs(User::factory()->kelompok($revised->organization)->create())->get('/kelompok')
        ->assertOk()->assertSee('Laporan September 2026 perlu diperbaiki')->assertDontSee('terlambat')->assertDontSee('lewat ');

    $this->actingAs(User::factory()->kelompok($missing)->create())->get('/kelompok')
        ->assertOk()->assertSee('Laporan September 2026 terlambat 4 hari');
});
