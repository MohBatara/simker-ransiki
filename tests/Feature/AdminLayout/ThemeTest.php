<?php

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use App\Support\Dashboards\Statistics;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jayapura'));
});

afterEach(fn () => Carbon::setTestNow());

test('persen perubahan hanya bila kedua bulan punya angka dan bulan sebelumnya bukan nol', function () {
    expect(Statistics::change(1500, 1000))->toBe(50)
        ->and(Statistics::change(500, 1000))->toBe(-50)
        ->and(Statistics::change(-200, -100))->toBe(-100)
        ->and(Statistics::change(1000, null))->toBeNull()
        ->and(Statistics::change(null, 1000))->toBeNull()
        ->and(Statistics::change(1000, 0))->toBeNull();
});

test('kerangka area aplikasi: tema, panel putih, alat kepala halaman, tanpa bilah atas terpisah', function () {
    $html = $this->actingAs(User::factory()->admin()->create())->get('/admin/kelompok')->assertOk()->getContent();

    expect($html)->toContain('<body class="theme-app')
        ->toContain('md:rounded-frame')
        ->toContain('id="cari-global"')
        ->toContain('data-bell="desktop"')
        ->not->toContain('sticky top-0 z-20 hidden h-16');
});

test('dasbor admin: kartu Ekonomi dengan persen perubahan dan grafik 6 bulan, lalu Laporan terbaru dari antrean', function () {
    $organization = Organization::factory()->create(['name' => 'KUB Tren', 'reporting_starts_on' => '2026-01-01']);
    MonthlyReport::factory()->for($organization)->status(ReportStatus::Verified)->create(['period' => '2026-08-01', 'submitted_at' => now(), 'income_sales' => 1000000, 'income_other' => 0, 'costs' => 400000]);
    MonthlyReport::factory()->for($organization)->status(ReportStatus::Verified)->create(['period' => '2026-09-01', 'submitted_at' => now(), 'income_sales' => 1500000, 'income_other' => 0, 'costs' => 400000]);
    $waiting = MonthlyReport::factory()->for(Organization::factory()->create(['name' => 'KUB Antre']))->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => now()]);
    $waiting->reviews()->forceCreate(['user_id' => User::factory()->admin()->create()->id, 'action' => 'submitted', 'created_at' => now()]);

    $response = $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()
        ->assertSee('Ekonomi September 2026')
        ->assertSee('6 bulan terakhir')
        ->assertSeeInOrder(['Penjualan', 'Rp 1,5 jt', '50%'])
        ->assertSee('naik dari bulan sebelumnya')
        ->assertSee('Laporan terbaru')
        ->assertSee('KUB Antre')
        ->assertSee('/admin/laporan/'.$waiting->id.'?dari=antrean', false)
        ->assertSee('Mulai verifikasi');

    expect($response->viewData('economyTrend'))->toHaveCount(6)
        ->and($response->viewData('economyTrend')->last()['totals']['income_sales'])->toBe(['sum' => 1500000, 'filled' => 1])
        ->and($response->viewData('latestReports')->pluck('id')->all())->toBe([$waiting->id]);
});

test('baris tabel bertautan: menu titik tiga berisi Lihat dan Ubah', function () {
    $organization = Organization::factory()->create(['name' => 'KUB Menu']);

    $this->actingAs(User::factory()->admin()->create())->get('/admin/kelompok')->assertOk()
        ->assertSee('aria-label="Tindakan untuk KUB Menu"', false)
        ->assertSeeInOrder(['Lihat', '/admin/kelompok/'.$organization->id.'/ubah'], false);
});
