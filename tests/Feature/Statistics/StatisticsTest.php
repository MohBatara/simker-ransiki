<?php

use App\Enums\LicenseStatus;
use App\Enums\ReportStatus;
use App\Models\BusinessSector;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\SimpleExcel\SimpleExcelReader;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jayapura'));

    $this->opdA = Opd::factory()->create(['name' => 'Dinas A']);
    $this->opdB = Opd::factory()->create(['name' => 'Dinas B']);
    $this->sector1 = BusinessSector::factory()->create(['name' => 'Bidang Satu']);
    $this->sector2 = BusinessSector::factory()->create(['name' => 'Bidang Dua']);

    // Dua bidang usaha dan dua OPD: tidak boleh terhitung ganda.
    $this->both = Organization::factory()->create(['name' => 'KUB Dua Bidang', 'reporting_starts_on' => '2026-07-01']);
    $this->both->opds()->attach([$this->opdA->id, $this->opdB->id]);
    $this->both->sectors()->attach([$this->sector1->id, $this->sector2->id]);

    $this->onlyA = Organization::factory()->create(['name' => 'KUB Binaan A', 'reporting_starts_on' => '2026-07-01']);
    $this->onlyA->opds()->attach($this->opdA);
    $this->onlyA->sectors()->attach($this->sector1);

    $this->onlyB = Organization::factory()->create(['name' => 'KUB Binaan B', 'reporting_starts_on' => '2026-07-01']);
    $this->onlyB->opds()->attach($this->opdB);
    $this->onlyB->sectors()->attach($this->sector2);

    $report = fn (Organization $organization, string $period, ReportStatus $status, array $attributes = []) => MonthlyReport::factory()
        ->for($organization)->status($status)
        ->create(['period' => $period, 'submitted_at' => $status === ReportStatus::Draft ? null : $period, ...$attributes]);

    $report($this->both, '2026-09-01', ReportStatus::Verified, ['income_sales' => 1_000_000])
        ->items()->create(['product_name' => 'Kakao', 'production_qty' => 10, 'production_unit' => 'kg']);
    $report($this->both, '2026-08-01', ReportStatus::Verified, ['income_sales' => null]);
    $report($this->both, '2026-07-01', ReportStatus::Submitted, ['income_sales' => 999_999]);
    $report($this->onlyA, '2026-09-01', ReportStatus::Verified, ['income_sales' => 0])
        ->items()->createMany([
            ['product_name' => ' kakao ', 'production_qty' => 5, 'production_unit' => 'KG'],
            ['product_name' => 'Kakao', 'production_qty' => 2, 'production_unit' => 'karung'],
        ]);
    $report($this->onlyA, '2026-08-01', ReportStatus::Draft, ['income_sales' => 400_000]);
    $report($this->onlyB, '2026-09-01', ReportStatus::Verified, ['income_sales' => 7_000_000]);

    $this->opdUser = User::factory()->opd($this->opdA)->create();
    $this->admin = User::factory()->admin()->create();
});

afterEach(fn () => Carbon::setTestNow());

function statisticsSheet(TestResponse $response, string $sheet): array
{
    return SimpleExcelReader::create($response->baseResponse->getFile()->getPathname(), 'xlsx')
        ->noHeaderRow()->fromSheetName($sheet)->getRows()->all();
}

test('OPD hanya melihat statistik kelompok binaannya, juga bila filter dimanipulasi', function () {
    foreach (['/opd/statistik', '/opd/statistik?opd='.$this->opdB->id, '/opd/statistik?opd=none&sector='.$this->sector2->id] as $url) {
        $response = $this->actingAs($this->opdUser)->get($url)->assertOk();
        $september = $response->viewData('incomeTrend')->firstWhere(fn ($row) => $row['period']->format('Y-m') === '2026-09');

        expect($response->viewData('filters'))->not->toHaveKey('opd')
            ->and($september['total'])->not->toBe(8_000_000)
            ->and($response->viewData('sectors')->max('total'))->toBeLessThanOrEqual(1_000_000);
    }

    $response = $this->actingAs($this->opdUser)->get('/opd/statistik?opd='.$this->opdB->id)->assertOk();
    expect($response->viewData('summary')['organizations'])->toBe(2)
        ->and($response->viewData('summary')['sales'])->toBe(1_000_000);

    $this->get('/opd/statistik/legalitas?opd='.$this->opdB->id)->assertOk()->assertSee('KUB Binaan A')->assertDontSee('KUB Binaan B');

    $rows = statisticsSheet($this->get('/opd/statistik/legalitas/unduh?opd='.$this->opdB->id)->assertDownload(), 'Kelompok');
    expect(collect($rows)->pluck(0)->all())->toBe(['Nama kelompok', 'KUB Binaan A', 'KUB Dua Bidang']);
});

test('hanya laporan terverifikasi yang dihitung, NULL bukan 0, bulan kosong = null', function () {
    $trend = $this->actingAs($this->opdUser)->get('/opd/statistik')->assertOk()->viewData('incomeTrend')
        ->keyBy(fn ($row) => $row['period']->format('Y-m'));

    // September: 1.000.000 + 0 (0 yang dikonfirmasi tetap dihitung), diisi 2 dari 2 kelompok.
    expect($trend['2026-09'])->toMatchArray(['total' => 1_000_000, 'filled' => 2, 'obligated' => 2])
        // Agustus: satu laporan terverifikasi dengan pendapatan NULL, draf 400.000 tidak dihitung.
        ->and($trend['2026-08'])->toMatchArray(['total' => null, 'filled' => 0, 'reports' => 1])
        // Juli: hanya laporan diajukan (belum terverifikasi).
        ->and($trend['2026-07']['total'])->toBeNull()
        ->and($trend)->toHaveCount(3);
});

test('rentang bulan: default 12 bulan dipotong di kewajiban lapor paling awal, nilai tidak sah kembali ke default', function () {
    $this->actingAs($this->admin);
    $months = fn (string $url) => $this->get($url)->assertOk()->viewData('incomeTrend')->map(fn ($row) => $row['period']->format('Y-m'))->all();

    expect($months('/admin/statistik'))->toBe(['2026-07', '2026-08', '2026-09'])
        ->and($months('/admin/statistik?dari=2026-08&sampai=2026-09'))->toBe(['2026-08', '2026-09'])
        ->and($months('/admin/statistik?dari=2026-09&sampai=2026-07'))->toBe(['2026-07', '2026-08', '2026-09'])
        ->and($months('/admin/statistik?dari=2026-08&sampai=2026-10'))->toBe(['2026-07', '2026-08', '2026-09'])
        ->and($months('/admin/statistik?dari=salah'))->toBe(['2026-07', '2026-08', '2026-09']);
});

test('kelompok dengan dua bidang usaha dan dua OPD tidak terhitung ganda di jumlah kelompok', function () {
    $this->actingAs($this->admin);

    expect($this->get('/admin/statistik')->viewData('summary')['organizations'])->toBe(3)
        ->and($this->get('/admin/statistik?opd='.$this->opdA->id)->viewData('summary')['organizations'])->toBe(2)
        ->and($this->get('/admin/statistik?sector='.$this->sector1->id)->viewData('summary')['organizations'])->toBe(2)
        ->and($this->get('/admin/statistik/legalitas')->viewData('legal')['total'])->toBe(3);

    // Per bidang: kelompok dua bidang dihitung di kedua bidangnya, dengan catatan wajib.
    $response = $this->get('/admin/statistik');
    $sectors = $response->viewData('sectors')->keyBy('label');
    expect($sectors['Bidang Satu'])->toMatchArray(['total' => 1_000_000, 'organizations' => 2])
        ->and($sectors['Bidang Dua'])->toMatchArray(['total' => 8_000_000, 'organizations' => 2]);
    $response->assertSee('Kelompok dengan lebih dari satu bidang usaha dihitung di setiap bidangnya');
});

test('kepatuhan pelaporan memakai status pelaporan Paket 1', function () {
    $compliance = $this->actingAs($this->opdUser)->get('/opd/statistik')->viewData('compliance')
        ->keyBy(fn ($row) => $row['period']->format('Y-m'));

    expect($compliance['2026-09'])->toMatchArray(['verified' => 2, 'submitted' => 0, 'revision' => 0, 'overdue' => 0, 'pending' => 0, 'obligated' => 2])
        // Draf setelah tenggat = terlambat.
        ->and($compliance['2026-08'])->toMatchArray(['verified' => 1, 'overdue' => 1, 'pending' => 0])
        ->and($compliance['2026-07'])->toMatchArray(['submitted' => 1, 'overdue' => 1]);
});

test('produksi dikelompokkan per produk + satuan, tidak dijumlah lintas satuan', function () {
    $production = $this->actingAs($this->opdUser)->get('/opd/statistik')->viewData('production');

    expect($production->map(fn ($row) => [$row['unit'], (float) $row['quantity'], $row['organizations']])->all())
        ->toBe([['kg', 15.0, 2], ['karung', 2.0, 1]]);
});

test('hitungan legalitas per kelompok unik', function () {
    foreach (['NIB', 'PIRT', 'Sertifikat Halal'] as $type) {
        $this->both->licenses()->create(['type' => $type, 'status' => LicenseStatus::Issued]);
    }
    $this->onlyA->licenses()->create(['type' => 'NIB', 'status' => LicenseStatus::NeedsFix]);
    $this->onlyA->licenses()->create(['type' => 'PIRT', 'status' => LicenseStatus::InProcess]);
    $this->onlyB->licenses()->create(['type' => 'NIB', 'status' => LicenseStatus::None]);

    $response = $this->actingAs($this->admin)->get('/admin/statistik/legalitas')->assertOk();

    expect($response->viewData('legal'))->toBe(['total' => 3, 'issued' => 1, 'in_process' => 1, 'needs_fix' => 1, 'without' => 1])
        ->and($response->viewData('byType')->sum('issued'))->toBe(1)
        ->and($response->viewData('needsFix')->pluck('name')->all())->toBe(['KUB Binaan A'])
        ->and($response->viewData('without')->pluck('name')->all())->toBe(['KUB Binaan B']);

    expect($this->actingAs($this->opdUser)->get('/opd/statistik/legalitas')->viewData('legal'))
        ->toBe(['total' => 2, 'issued' => 1, 'in_process' => 1, 'needs_fix' => 1, 'without' => 0]);
});

test('izin yang akan habis dalam 60 hari; tanggal kosong tidak dianggap habis', function () {
    $this->onlyA->licenses()->create(['type' => 'NIB', 'status' => LicenseStatus::Issued, 'expires_on' => '2026-11-30']);
    $this->onlyA->licenses()->create(['type' => 'PIRT', 'status' => LicenseStatus::Issued, 'expires_on' => '2027-06-30']);
    $this->onlyA->licenses()->create(['type' => 'Halal', 'status' => LicenseStatus::Issued, 'expires_on' => null]);
    $this->onlyB->licenses()->create(['type' => 'BPOM', 'status' => LicenseStatus::Issued, 'expires_on' => '2026-11-01']);

    $response = $this->actingAs($this->opdUser)->get('/opd/statistik/legalitas')->assertOk();

    expect($response->viewData('expiring')->pluck('type')->all())->toBe(['NIB']);
    $response->assertSee('Akan habis dalam 60 hari')->assertSee('41 hari lagi')->assertDontSee('BPOM');
});

test('unduh Excel rekap legalitas memakai filter dan hitungan yang sama', function () {
    $this->both->licenses()->create(['type' => 'NIB', 'status' => LicenseStatus::Issued, 'expires_on' => '2027-01-31']);

    $response = $this->actingAs($this->admin)->get('/admin/statistik/legalitas/unduh?sector='.$this->sector1->id)
        ->assertOk()->assertDownload('rekap-legalitas-2026-10-20.xlsx');

    $summary = collect(statisticsSheet($response, 'Ringkasan'))->mapWithKeys(fn ($row) => [$row[0] => $row[1] ?? null]);
    expect($summary['Kelompok'])->toEqual(2)
        ->and($summary['Punya minimal satu izin terbit'])->toEqual(1)
        ->and($summary['Belum punya izin'])->toEqual(1);

    $rows = statisticsSheet($response, 'Kelompok');
    expect($rows[2][6])->toBe('NIB (Terbit, berlaku sampai 31 Januari 2027)');

    $this->get('/admin/statistik/legalitas?sector='.$this->sector1->id)
        ->assertSee('/admin/statistik/legalitas/unduh?sector='.$this->sector1->id, false);
});

test('halaman statistik tertutup bagi peran lain', function () {
    $kelompok = User::factory()->kelompok($this->both)->create();

    $this->actingAs($kelompok)->get('/admin/statistik')->assertForbidden();
    $this->actingAs($kelompok)->get('/opd/statistik/legalitas')->assertForbidden();
    $this->actingAs($kelompok)->get('/opd/statistik/legalitas/unduh')->assertForbidden();
    $this->actingAs($this->opdUser)->get('/admin/statistik')->assertForbidden();
    $this->actingAs($this->admin)->get('/opd/statistik')->assertForbidden();
    auth()->logout();
    $this->get('/admin/statistik')->assertRedirect('/login');
});

test('halaman statistik memuat grafik, tombol Lihat tabel, dan keadaan kosong', function () {
    DB::enableQueryLog();
    $response = $this->actingAs($this->admin)->get('/admin/statistik')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(30);
    $response->assertSee('Tren pendapatan penjualan')
        ->assertSee('Kepatuhan pelaporan')
        ->assertSee('Pendapatan per bidang usaha')
        ->assertSee('Produksi per produk')
        ->assertSee('Lihat tabel')
        ->assertSee('Dari laporan terverifikasi.')
        ->assertSee('x-data="chart(', false)
        // Belum ada masalah yang dicatat: pesan, bukan grafik kosong.
        ->assertSee('Tidak ada masalah yang dicatat di laporan terverifikasi pada rentang ini.');

    MonthlyReport::query()->update(['status' => ReportStatus::Submitted]);
    $this->get('/admin/statistik')->assertOk()
        ->assertSee('Belum ada laporan terverifikasi yang mengisi pendapatan penjualan pada rentang ini.')
        ->assertSee('Belum ada laporan terverifikasi yang mengisi produksi pada rentang ini.');
});

test('dasbor kelompok menampilkan tren pendapatan dan produksi miliknya sendiri', function () {
    $response = $this->actingAs(User::factory()->kelompok($this->onlyA)->create())->get('/kelompok')->assertOk();
    $trend = $response->viewData('incomeTrend')->keyBy(fn ($row) => $row['period']->format('Y-m'));

    expect($trend)->toHaveCount(12)
        ->and($trend['2026-09']['total'])->toBe(0)
        ->and($trend['2026-08']['total'])->toBeNull()
        ->and($response->viewData('productionTrend')->map(fn ($row) => [$row['product'], $row['unit']])->all())->toBe([['kakao', 'kg'], ['Kakao', 'karung']]);

    $response->assertSee('Pendapatan penjualan, 12 bulan')->assertSee('Produksi, 12 bulan')->assertDontSee('7.000.000');
});

test('menu Statistik tampil untuk Admin dan OPD', function () {
    $this->actingAs($this->admin)->get('/admin')->assertSee('aria-label="Statistik"', false);
    $this->actingAs($this->opdUser)->get('/opd')->assertSee('aria-label="Statistik"', false);
});

test('form izin menyimpan tanggal berlaku sampai, tidak boleh sebelum tanggal terbit', function () {
    $this->actingAs($this->admin);
    $url = '/admin/kelompok/'.$this->onlyA->id.'/izin';
    $data = ['type' => 'NIB', 'status' => 'issued', 'issued_on' => '2026-08-01'];

    $this->post($url, [...$data, 'expires_on' => '2026-07-01'])->assertSessionHasErrors('expires_on');
    $this->post($url, [...$data, 'expires_on' => '2028-08-01'])->assertSessionHasNoErrors();
    $this->post($url, $data)->assertSessionHasNoErrors();

    expect($this->onlyA->licenses()->orderBy('id')->pluck('expires_on')->map(fn ($date) => $date?->toDateString())->all())
        ->toBe(['2028-08-01', null]);
});

test('bulan sementara: belum lewat tenggat + 14 hari, atau cakupan terverifikasi di bawah 80%', function () {
    $month = fn (string $value) => \Carbon\CarbonImmutable::parse($value);

    expect(App\Support\Dashboards\Statistics::isProvisional($month('2026-09-01'), 2, 2))->toBeTrue()
        ->and(App\Support\Dashboards\Statistics::isProvisional($month('2026-08-01'), 1, 2))->toBeTrue()
        ->and(App\Support\Dashboards\Statistics::isProvisional($month('2026-08-01'), 4, 5))->toBeFalse()
        ->and(App\Support\Dashboards\Statistics::isProvisional($month('2026-08-01'), 0, 0))->toBeFalse();

    config(['simker.statistics.provisional_min_coverage' => 0.5]);
    expect(App\Support\Dashboards\Statistics::isProvisional($month('2026-08-01'), 1, 2))->toBeFalse();

    // Tanggal 25 Oktober: September sudah lewat tenggat + 14 hari.
    Carbon::setTestNow(Carbon::parse('2026-10-25 12:00:00', 'Asia/Jayapura'));
    expect(App\Support\Dashboards\Statistics::isProvisional($month('2026-09-01'), 2, 2))->toBeFalse();
});

test('grafik tren menandai bulan sementara di tooltip, catatan, dan tabel', function () {
    $response = $this->actingAs($this->opdUser)->get('/opd/statistik')->assertOk();
    $chart = App\Support\Dashboards\StatisticsCharts::incomeTrend($response->viewData('incomeTrend'));

    expect($chart['datasets'][0]['provisional'])->toBe([true, true, true])
        ->and($chart['datasets'][0]['notes'][1])->toBe(['Diisi 0 dari 2 kelompok', 'Sementara: baru 1 dari 2 kelompok terverifikasi'])
        ->and($chart['table']['columns'])->toBe(['Bulan', 'Pendapatan penjualan', 'Cakupan', 'Keterangan'])
        ->and(collect($chart['table']['rows'])->pluck(3)->unique()->all())->toBe(['Sementara']);
    $response->assertSee('Garis putus-putus = data bulan itu belum lengkap.');
});

test('grafik produksi kelompok: satuan berbeda dipisah, legenda dan sumbu Y bersatuan', function () {
    $this->actingAs(User::factory()->kelompok($this->onlyA)->create());
    $response = $this->get('/kelompok')->assertOk();
    $chart = App\Support\Dashboards\StatisticsCharts::productionTrend($response->viewData('productionTrend'), $response->viewData('months'));

    expect(collect($chart['panels'])->map(fn ($panel) => [$panel['subtitle'], $panel['yTitle'], count($panel['datasets'])])->all())
        ->toBe([['kakao (kg)', 'kg', 1], ['Kakao (karung)', 'karung', 1]])
        ->and($chart['panels'][0]['legend']['always'])->toBeTrue();
    $response->assertSee('Satuan kedua produk berbeda, sehingga setiap produk punya grafik sendiri.')
        ->assertSee('data-panel="1"', false);

    // Satuan sama: satu grafik berisi dua garis.
    $this->onlyA->monthlyReports()->whereDate("period", "2026-09-01")->first()->items()->where('production_unit', 'karung')->update(['production_unit' => 'kg', 'product_name' => 'Kopi']);
    $chart = App\Support\Dashboards\StatisticsCharts::productionTrend($this->get('/kelompok')->viewData('productionTrend'), $response->viewData('months'));
    expect($chart['panels'])->toHaveCount(1)
        ->and($chart['panels'][0]['yTitle'])->toBe('kg')
        ->and($chart['footnote'])->toBe('Dua produk dengan produksi terbesar, satuannya sama.');
});

test('kepatuhan pelaporan: urutan tumpukan tetap dan legenda memuat jumlah bulan terakhir', function () {
    $chart = App\Support\Dashboards\StatisticsCharts::compliance($this->actingAs($this->opdUser)->get('/opd/statistik')->viewData('compliance'));

    expect(collect($chart['datasets'])->pluck('label')->all())->toBe(['Terverifikasi', 'Menunggu verifikasi', 'Perlu revisi', 'Terlambat', 'Belum diajukan'])
        ->and($chart['legend'])->toBe(['position' => 'top', 'title' => 'Jumlah September 2026:', 'lastValue' => true]);
});

test('tabel produksi di HP: satuan ikut di baris Jumlah', function () {
    $this->actingAs($this->opdUser)->get('/opd/statistik')->assertOk()
        ->assertSee('data-label="Jumlah"', false)
        ->assertSee('15<span class="md:hidden"> kg</span>', false);
});

test('tabel lebar muat di tablet: judul kolom boleh dua baris, kolom turunan opsional, wadah gulir berlabel', function () {
    $chart = App\Support\Dashboards\StatisticsCharts::compliance($this->actingAs($this->admin)->get('/admin/statistik')->viewData('compliance'));
    expect($chart['table']['columns'][6])->toBe('Wajib lapor')->and($chart['table']['optional'])->toBe([6]);

    $this->get('/admin/statistik')->assertSee('role="region" aria-label="Tabel kepatuhan pelaporan" tabindex="0"', false);
    $this->get('/admin/statistik/legalitas')->assertSee('aria-label="Legalitas per kampung"', false);
});
