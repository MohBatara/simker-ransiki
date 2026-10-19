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

test('dasbor admin: perlu dikerjakan, kartu angka, progres per OPD, kelompok, dan ekonomi resmi', function () {
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
        ->assertSee('Perlu dikerjakan')
        ->assertSee('2 kelompok belum punya OPD pembina')
        ->assertSee('/admin/kelompok?opd=none', false)
        ->assertSee('Buat akun 1 kelompok')
        ->assertSee('/admin/kelompok?status=active&amp;akun=tidak-ada', false)
        // Tanpa laporan menunggu: tugas verifikasi tidak muncul.
        ->assertDontSee('laporan menunggu verifikasi')
        ->assertSee('Pelaporan September 2026, 2 kelompok wajib lapor')
        ->assertSee('1 dari 2')
        ->assertSee('DC')
        ->assertSee('Tanpa OPD pembina')
        ->assertSee('3')
        ->assertSee('Menurut jenis kelembagaan')
        ->assertSee('Rp 1,3 jt')
        ->assertSee('title="Rp 1.250.000"', false)
        // Semua laporan mengisi penjualan: keterangan "diisi X dari Y" tidak ditampilkan.
        ->assertDontSee('diisi 1 dari 1 laporan')
        ->assertSee('1 dari 2')
        ->assertSee('kelompok wajib lapor terverifikasi')
        ->assertSee('50%')
        ->assertDontSee('Angka belum mewakili seluruh kelompok')
        ->assertSee('Belum ada yang mengisi')
        ->assertSee('Kakao kering')
        ->assertSee('12,5 kg');

    expect($response->viewData('attention'))->toMatchArray(['without_opd' => 2, 'admin_queue' => 0, 'waiting' => 0, 'active_without_account' => 1])
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

test('tautan dasbor admin tidak di-escape ganda', function () {
    $organization = Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);
    $organization->opds()->attach(Opd::factory()->create());

    $hrefs = collect(hrefsIn($this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()->getContent()));

    expect($hrefs->filter(fn ($href) => str_contains($href, '&amp;'))->all())->toBe([]);

    parse_str((string) parse_url($hrefs->first(fn ($href) => str_contains($href, 'laporan=belum-diajukan')), PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['laporan' => 'belum-diajukan', 'periode' => '2026-09']);
});

test('rupiah ringkas untuk kotak angka utama: rb, jt, M', function () {
    expect(App\Support\Format::rupiahShort(850000))->toBe('Rp 850 rb')
        ->and(App\Support\Format::rupiahShort(121_400_000))->toBe('Rp 121,4 jt')
        ->and(App\Support\Format::rupiahShort(2_000_000))->toBe('Rp 2 jt')
        ->and(App\Support\Format::rupiahShort(1_250_000_000))->toBe('Rp 1,3 M')
        ->and(App\Support\Format::rupiahShort(-500))->toBe('Rp 500');
});

test('kartu Ekonomi: cakupan di bawah 50% diberi catatan, laba negatif ditulis Rugi, kartu Kelompok bersegmen', function () {
    foreach (range(1, 3) as $i) {
        Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);
    }
    $organization = Organization::factory()->create(['reporting_starts_on' => '2026-07-01', 'status' => OrganizationStatus::Inactive]);
    MonthlyReport::factory()->for($organization)->status(ReportStatus::Verified)->create([
        'period' => '2026-09-01', 'submitted_at' => '2026-10-05 10:00:00',
        'income_sales' => 100000, 'income_other' => 0, 'costs' => 400000,
    ]);

    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()
        ->assertSee('25%')
        ->assertSee('Angka belum mewakili seluruh kelompok')
        ->assertSee('Rugi Rp 300 rb')
        ->assertSee('title="Rugi Rp 300.000"', false)
        ->assertSee('/admin/kelompok?status=inactive', false)
        ->assertSeeInOrder(['Aktif', '3', 'Tidak aktif', '1', 'Ditutup', '0']);
});

test('kartu Ekonomi kosong: ajakan membuka laporan yang menunggu verifikasi', function () {
    Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);

    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()
        ->assertSee('Belum ada laporan terverifikasi untuk September 2026.')
        ->assertSee('Buka laporan yang menunggu verifikasi');
});
