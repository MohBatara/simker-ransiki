<?php

use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jayapura'));
});

afterEach(fn () => Carbon::setTestNow());

test('dasbor admin: perhatian, progres per OPD, kelompok, dan ekonomi resmi', function () {
    $opd = Opd::factory()->create(['name' => 'Dinas Contoh', 'short_name' => 'DC']);
    $withOpd = Organization::factory()->create(['name' => 'KUB Satu', 'reporting_starts_on' => '2026-07-01']);
    $withOpd->opds()->attach($opd);
    User::factory()->kelompok($withOpd)->create();
    $report = MonthlyReport::factory()->for($withOpd)->status(ReportStatus::Verified)->create([
        'period' => '2026-09-01', 'submitted_at' => '2026-10-05 10:00:00',
        'income_sales' => 1250000, 'income_other' => null, 'costs' => 250000,
    ]);
    $report->items()->create(['product_name' => 'Kakao kering', 'production_qty' => 12.5, 'production_unit' => 'kg']);
    Organization::factory()->create(['name' => 'KUB Tanpa OPD', 'reporting_starts_on' => '2026-07-01']);
    Organization::factory()->create(['status' => OrganizationStatus::Closed]);
    $this->actingAs(User::factory()->admin()->create());

    DB::enableQueryLog();
    $response = $this->get('/admin');
    $queries = count(DB::getQueryLog());

    $response->assertOk()
        ->assertSee('Perlu perhatian')
        ->assertSee('/admin/kelompok?opd=none', false)
        ->assertSee('/admin/kelompok?status=active&amp;akun=tidak-ada', false)
        ->assertSee('/admin/laporan?status=submitted&amp;menunggu=7', false)
        ->assertSee('Pelaporan September 2026')
        ->assertSee('1 dari 2')
        ->assertSee('DC')
        ->assertSee('Tanpa OPD pembina')
        ->assertSee('3')
        ->assertSee('kelompok terdaftar')
        ->assertSee('Rp 1.250.000')
        ->assertSee('dari 1 dari 1 laporan yang mengisi')
        ->assertSee('Belum ada yang mengisi')
        ->assertSee('Kakao kering')
        ->assertSee('12,5 kg');

    expect($response->viewData('attention'))->toBe(['without_opd' => 2, 'waiting' => 0, 'active_without_account' => 1])
        ->and($response->viewData('economy')['totals']['profit'])->toBe(['sum' => null, 'filled' => 0])
        ->and($queries)->toBeLessThan(35);
});

test('filter laporan tanpa OPD pembina', function () {
    $opd = Opd::factory()->create();
    $with = Organization::factory()->create();
    $with->opds()->attach($opd);
    $without = Organization::factory()->create();
    MonthlyReport::factory()->for($with)->status(ReportStatus::Submitted)->create(['submitted_at' => now()]);
    $orphan = MonthlyReport::factory()->for($without)->status(ReportStatus::Submitted)->create(['submitted_at' => now()]);

    $this->actingAs(User::factory()->admin()->create());
    expect($this->get('/admin/laporan?opd=none')->viewData('reports')->pluck('id')->all())->toBe([$orphan->id]);
});

test('peran lain tidak bisa membuka dasbor admin', function () {
    $this->actingAs(User::factory()->opd(Opd::factory()->create())->create())->get('/admin')->assertForbidden();
});

test('ketiga dasbor terbuka dengan data contoh, angka admin sama dengan jumlah per status', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Jayapura'));
    $this->seed(DemoSeeder::class);

    foreach (['admin' => '/admin', 'opd.pertanian' => '/opd', 'kelompok1' => '/kelompok', 'kelompok2' => '/kelompok'] as $username => $url) {
        $this->actingAs(User::where('username', $username)->firstOrFail())->get($url)->assertOk();
    }

    $this->actingAs(User::where('username', 'admin')->firstOrFail());
    $progress = $this->get('/admin')->viewData('progress');
    $expected = Organization::obligatedFor(Carbon::parse('2026-09-01'))->count();

    expect($progress['total'])->toBe($expected)
        ->and($progress['verified'])->toBe(MonthlyReport::where('status', 'verified')->whereDate('period', '2026-09-01')
        ->whereIn('organization_id', Organization::obligatedFor(Carbon::parse('2026-09-01'))->select('id'))->count());
});
