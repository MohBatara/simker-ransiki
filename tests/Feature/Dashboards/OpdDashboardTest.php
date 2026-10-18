<?php

use App\Enums\IssueKind;
use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\ReportReview;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jayapura'));
    $this->opd = Opd::factory()->create(['name' => 'Dinas Contoh']);
    $this->other = Opd::factory()->create();

    $make = function (string $name, Opd $opd) {
        $organization = Organization::factory()->create(['name' => $name, 'reporting_starts_on' => '2026-07-01']);
        $organization->opds()->attach($opd);

        return $organization;
    };
    $this->waiting = $make('KUB Menunggu', $this->opd);
    $this->report = MonthlyReport::factory()->for($this->waiting)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => '2026-10-08 09:00:00']);
    ReportReview::forceCreate(['monthly_report_id' => $this->report->id, 'action' => ReviewAction::Submitted, 'created_at' => '2026-10-08 09:00:00']);
    $this->report->issues()->create(['kind' => IssueKind::Problem, 'category' => 'modal', 'description' => 'x']);
    $make('KUB Belum Lapor', $this->opd);
    $foreign = $make('KUB OPD Lain', $this->other);
    MonthlyReport::factory()->for($foreign)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => now()]);

    $this->actingAs(User::factory()->opd($this->opd)->create());
});

afterEach(fn () => Carbon::setTestNow());

test('perlu dikerjakan, kartu angka, belum mengajukan, masalah, dan grafik hanya untuk binaan', function () {
    DB::enableQueryLog();
    $response = $this->get('/opd');
    $queries = count(DB::getQueryLog());

    $response->assertOk()
        ->assertSee('Perlu dikerjakan')
        ->assertSee('1 laporan menunggu verifikasi')
        ->assertSee('Verifikasi 1 laporan')
        ->assertSee('KUB Menunggu, September 2026')
        ->assertSee('12 hari')
        ->assertSee('1 menunggu lebih dari 7 hari')
        ->assertSee('Wajib lapor September 2026')
        ->assertSee('dari 2 kelompok wajib lapor')
        ->assertSee('1 kelompok binaan terlambat melapor September 2026')
        // Tenggat sudah lewat: daftar "Belum mengajukan" menjadi daftar terlambat.
        ->assertSee('Terlambat melapor September 2026')
        ->assertSee('Tenggat 10 Oktober 2026 · lewat 10 hari')
        ->assertSee('KUB Belum Lapor')
        ->assertSee('/opd/laporan/masalah?kind=problem&amp;category=modal&amp;period=2026-09', false)
        ->assertSee('/opd/kelompok?laporan=terlambat&amp;periode=2026-09', false)
        ->assertSee('Tren pendapatan, 12 bulan')->assertSee('Kepatuhan pelaporan, 6 bulan')->assertSee('Lihat statistik lengkap')
        ->assertDontSee('KUB OPD Lain');

    expect($response->viewData('waitingCount'))->toBe(1)
        ->and($response->viewData('progress'))->toBe(['verified' => 0, 'submitted' => 1, 'revision' => 0, 'draft' => 0, 'missing' => 1, 'total' => 2])
        ->and($queries)->toBeLessThan(30);
});

test('pemilih bulan: bulan lain bisa dipilih, nilai tidak sah kembali ke bulan wajib lapor', function () {
    expect($this->get('/opd?periode=2026-08')->viewData('period')->format('Y-m'))->toBe('2026-08')
        ->and($this->get('/opd?periode=2026-10')->viewData('period')->format('Y-m'))->toBe('2026-09')
        ->and($this->get('/opd?periode=salah')->viewData('period')->format('Y-m'))->toBe('2026-09');

    $this->get('/opd?periode=2026-08')->assertSee('Wajib lapor Agustus 2026')->assertSee('Terlambat melapor Agustus 2026');
});

test('tanpa laporan menunggu', function () {
    $this->report->forceFill(['status' => ReportStatus::Verified])->save();

    $this->get('/opd')->assertOk()->assertSee('Perlu dikerjakan')->assertDontSee('laporan menunggu verifikasi')->assertDontSee('Verifikasi 1 laporan');
});

test('peran lain tidak bisa membuka dasbor OPD', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/opd')->assertForbidden();
    $this->actingAs(User::factory()->kelompok($this->waiting)->create())->get('/opd')->assertForbidden();
});

test('filter daftar kelompok dari dasbor: belum dibuat dan belum diajukan', function () {
    $draft = Organization::factory()->create(['name' => 'KUB Draf', 'reporting_starts_on' => '2026-07-01']);
    $draft->opds()->attach($this->opd);
    MonthlyReport::factory()->for($draft)->create(['period' => '2026-09-01']);

    $names = fn (string $query) => $this->get('/opd/kelompok?'.$query)->viewData('organizations')->pluck('name')->all();

    expect($names('laporan=belum-dibuat&periode=2026-09'))->toBe(['KUB Belum Lapor'])
        ->and($names('laporan=belum-diajukan&periode=2026-09'))->toBe(['KUB Belum Lapor', 'KUB Draf'])
        ->and($names('laporan=belum-dibuat&periode=salah'))->toHaveCount(3);

    $this->get('/opd/kelompok?laporan=belum-dibuat&periode=2026-09')
        ->assertSee('Belum membuat laporan September 2026')
        ->assertSee('aria-label="Hapus filter Belum membuat laporan September 2026"', false)
        ->assertSee('1 kelompok');
});

test('filter laporan menunggu lebih dari 7 hari', function () {
    $recent = MonthlyReport::factory()->for($this->waiting)->status(ReportStatus::Submitted)->create(['period' => '2026-08-01', 'submitted_at' => '2026-10-18 09:00:00']);
    ReportReview::forceCreate(['monthly_report_id' => $recent->id, 'action' => ReviewAction::Submitted, 'created_at' => '2026-10-18 09:00:00']);

    expect($this->get('/opd/laporan?status=submitted&menunggu=7')->viewData('reports')->pluck('id')->all())->toBe([$this->report->id]);
});

test('query string tautan dasbor terbaca benar (tidak di-escape ganda)', function () {
    $hrefs = hrefsIn($this->get('/opd')->assertOk()->getContent());

    expect($hrefs)->not->toBeEmpty()
        ->and(collect($hrefs)->filter(fn ($href) => str_contains($href, '&amp;'))->all())->toBe([]);

    $link = collect($hrefs)->first(fn ($href) => str_contains($href, 'laporan=terlambat'));
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

    expect($query)->toBe(['laporan' => 'terlambat', 'periode' => '2026-09'])
        ->and($this->get($link)->viewData('organizations')->pluck('name')->all())->toBe(['KUB Belum Lapor']);
});
