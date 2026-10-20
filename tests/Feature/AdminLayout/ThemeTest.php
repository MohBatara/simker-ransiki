<?php

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use App\Support\Dashboards\Statistics;
use App\Support\Format;
use Carbon\CarbonImmutable;
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
        ->toContain('data-search-open')
        ->toContain('data-bell="desktop"')
        ->not->toContain('sticky top-0 z-20 hidden h-16');
});

test('dasbor admin: kartu Ekonomi dengan grafik 6 bulan, lalu Laporan terbaru dari antrean', function () {
    $organization = Organization::factory()->create(['name' => 'KUB Tren', 'reporting_starts_on' => '2026-01-01']);
    MonthlyReport::factory()->for($organization)->status(ReportStatus::Verified)->create(['period' => '2026-08-01', 'submitted_at' => now(), 'income_sales' => 1000000, 'income_other' => 0, 'costs' => 400000]);
    MonthlyReport::factory()->for($organization)->status(ReportStatus::Verified)->create(['period' => '2026-09-01', 'submitted_at' => now(), 'income_sales' => 1500000, 'income_other' => 0, 'costs' => 400000]);
    $waiting = MonthlyReport::factory()->for(Organization::factory()->create(['name' => 'KUB Antre']))->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => now()]);
    $waiting->reviews()->forceCreate(['user_id' => User::factory()->admin()->create()->id, 'action' => 'submitted', 'created_at' => now()]);

    $response = $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()
        ->assertSee('Ekonomi September 2026')
        ->assertSee('6 bulan terakhir')
        ->assertSee('Rp 1,5 jt')
        // Hanya 1 kelompok yang sama di kedua bulan: persen disembunyikan, catatannya sekali di bawah ketiga angka.
        ->assertSee('Belum bisa dibandingkan dengan Agustus 2026')
        ->assertDontSee('>belum bisa dibandingkan<', false)
        ->assertSee('Laporan terbaru')
        ->assertSee('KUB Antre')
        ->assertSee('/admin/laporan/'.$waiting->id.'?dari=antrean', false)
        ->assertSee('Mulai verifikasi');

    expect($response->viewData('economyTrend'))->toHaveCount(6)
        ->and($response->viewData('economyTrend')->last()['totals']['income_sales'])->toBe(['sum' => 1500000, 'filled' => 1])
        ->and($response->viewData('latestReports')->pluck('id')->all())->toBe([$waiting->id]);
});

test('perbandingan setara: hanya kelompok terverifikasi di kedua bulan yang mengisi angka di keduanya', function () {
    $admin = User::factory()->admin()->create();
    $report = fn (Organization $organization, string $period, array $values, ReportStatus $status = ReportStatus::Verified) => MonthlyReport::factory()->for($organization)->status($status)
        ->create(['period' => $period, 'submitted_at' => now(), 'income_other' => 0, 'costs' => 100, ...$values]);

    // Lima kelompok yang sama: 1.000 → 1.500 masing-masing (naik 50%).
    foreach (range(1, 5) as $i) {
        $organization = Organization::factory()->create();
        $report($organization, '2026-08-01', ['income_sales' => 1000]);
        $report($organization, '2026-09-01', ['income_sales' => 1500]);
    }
    // Hanya terverifikasi bulan ini: tidak ikut dibandingkan meski angkanya besar.
    $report(Organization::factory()->create(), '2026-09-01', ['income_sales' => 900000]);
    // Bulan lalu belum terverifikasi: tidak ikut.
    $pending = Organization::factory()->create();
    $report($pending, '2026-08-01', ['income_sales' => 10], ReportStatus::Submitted);
    $report($pending, '2026-09-01', ['income_sales' => 900000]);
    // Penjualan kosong di salah satu bulan: tidak ikut untuk penjualan, tetap ikut untuk biaya.
    $partial = Organization::factory()->create();
    $report($partial, '2026-08-01', ['income_sales' => null]);
    $report($partial, '2026-09-01', ['income_sales' => 700000]);

    $comparison = (new Statistics($admin, [], CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-01')))
        ->matchedChange(CarbonImmutable::parse('2026-09-01'));

    expect($comparison['income_sales'])->toMatchArray(['change' => 50, 'organizations' => 5, 'comparable' => true])
        ->and($comparison['costs'])->toMatchArray(['change' => 0, 'organizations' => 6, 'comparable' => true])
        ->and($comparison['income_sales']['previous']->format('Y-m'))->toBe('2026-08');

    $this->actingAs($admin)->get('/admin')->assertOk()
        ->assertSee('title="Dibanding Agustus 2026, dari 5 kelompok yang sama"', false)
        ->assertSee('naik. Dibanding Agustus 2026, dari 5 kelompok yang sama.');
});

test('perbandingan setara dengan kurang dari 5 kelompok yang sama: persen tidak dihitung', function () {
    $admin = User::factory()->admin()->create();
    foreach (range(1, 4) as $i) {
        $organization = Organization::factory()->create();
        MonthlyReport::factory()->for($organization)->status(ReportStatus::Verified)->create(['period' => '2026-08-01', 'submitted_at' => now(), 'income_sales' => 1000]);
        MonthlyReport::factory()->for($organization)->status(ReportStatus::Verified)->create(['period' => '2026-09-01', 'submitted_at' => now(), 'income_sales' => 2000]);
    }

    $comparison = (new Statistics($admin))->matchedChange(CarbonImmutable::parse('2026-09-01'));

    expect($comparison['income_sales'])->toMatchArray(['change' => null, 'organizations' => 4, 'comparable' => false]);
});

test('nilai kosong di area aplikasi tampil "Belum diisi", bukan tanda pisah', function () {
    expect((string) Format::orEmpty(Format::rupiah(null)))->toContain('Belum diisi')->toContain('text-ink-3')
        ->and(Format::orEmpty('Rp 1.000'))->toBe('Rp 1.000')
        ->and(Format::monthShort('2026-09-01'))->toBe('Sep 2026')
        ->and(Format::village('Kampung Sumber Air'))->toBe('Sumber Air');

    $organization = Organization::factory()->create(['members_count' => null, 'leader_name' => 'Ketua Contoh']);
    $html = $this->actingAs(User::factory()->admin()->create())->get('/admin/kelompok')->assertOk()
        ->assertSee('Ketua: Ketua Contoh')
        ->getContent();
    $table = substr($html, strpos($html, 'id="daftar-kelompok"'));

    expect($table)->toContain('Belum diisi')->not->toContain('>—<');
});

test('pencarian umum lewat tombol ikon dan dialog (Ctrl/Cmd + K), kotak cari daftar tetap', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin/kelompok')->assertOk()
        ->assertSee('data-search-open', false)
        ->assertSee('x-on:keydown.window.ctrl.k.prevent', false)
        ->assertSee('id="cari-global"', false)
        ->assertSee('action="'.route('admin.search').'"', false)
        ->assertSee('Cari nama kelompok');

    // Kelompok tidak mendapat pencarian umum.
    $organization = Organization::factory()->create();
    $this->actingAs(User::factory()->kelompok($organization)->create())->get('/kelompok')->assertOk()
        ->assertDontSee('data-search-open', false);
});

test('halaman utama bertab tanpa remah; halaman detail tetap memakai remah', function () {
    $organization = Organization::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/kelompok')->assertOk()->assertDontSee('aria-label="Remah"', false);
    $this->get('/admin/kelompok/'.$organization->id)->assertOk()->assertSee('aria-label="Remah"', false);
});

test('baris tabel kelompok: menu titik tiga berisi Buka detail dan Ubah', function () {
    $organization = Organization::factory()->create(['name' => 'KUB Menu']);

    $this->actingAs(User::factory()->admin()->create())->get('/admin/kelompok')->assertOk()
        ->assertSee('aria-label="Tindakan untuk KUB Menu"', false)
        ->assertSeeInOrder(['Buka detail', '/admin/kelompok/'.$organization->id.'/ubah'], false);
});
