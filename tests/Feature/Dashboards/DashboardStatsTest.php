<?php

use App\Enums\IssueKind;
use App\Enums\LegalStatus;
use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ReportReview;
use App\Models\User;
use App\Support\Dashboards\DistrictStats;
use App\Support\Dashboards\KelompokSummary;
use App\Support\Dashboards\ReportingPeriod;
use App\Support\Dashboards\ReportingStats;
use App\Support\Dashboards\Statistics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Jayapura'));
    $this->period = CarbonImmutable::parse('2026-09-01');

    $this->opdA = Opd::factory()->create(['name' => 'OPD A']);
    $this->opdB = Opd::factory()->create(['name' => 'OPD B']);

    $org = function (string $name, array $opds, OrganizationStatus $status = OrganizationStatus::Active, string $starts = '2026-07-01') {
        $organization = Organization::factory()->create(['name' => $name, 'status' => $status, 'reporting_starts_on' => $starts]);
        $organization->opds()->attach(collect($opds)->pluck('id'));

        return $organization;
    };
    $report = fn (Organization $organization, ReportStatus $status, array $attributes = []) => MonthlyReport::factory()->for($organization)->status($status)->create([
        'period' => '2026-09-01',
        'submitted_at' => $status === ReportStatus::Draft ? null : '2026-09-30 10:00:00',
        ...$attributes,
    ]);

    $this->o1 = $org('Satu', [$this->opdA, $this->opdB]);
    $this->r1 = $report($this->o1, ReportStatus::Verified, ['income_sales' => 100000, 'income_other' => null, 'costs' => 40000]);
    $this->o2 = $org('Dua', [$this->opdA]);
    $this->r2 = $report($this->o2, ReportStatus::Submitted);
    $this->o3 = $org('Tiga', [$this->opdA], OrganizationStatus::Inactive);
    $this->r3 = $report($this->o3, ReportStatus::Revision);
    $this->o4 = $org('Empat', [$this->opdA]);
    $report($this->o4, ReportStatus::Draft);
    $this->o5 = $org('Lima', [$this->opdA]);
    $org('Enam tutup', [$this->opdA], OrganizationStatus::Closed);
    $org('Tujuh baru', [$this->opdA], starts: '2026-10-01');
    $this->o8 = $org('Delapan', [$this->opdB]);
    $this->r8 = $report($this->o8, ReportStatus::Verified, ['income_sales' => 200000, 'income_other' => 0, 'costs' => 50000]);
    $this->o9 = $org('Sembilan tanpa OPD', []);

    $this->opdUser = User::factory()->opd($this->opdA)->create();
    $this->admin = User::factory()->admin()->create();
});

afterEach(fn () => Carbon::setTestNow());

test('progres OPD hanya kelompok binaan wajib lapor; tutup dan yang belum mulai tidak dihitung', function () {
    expect((new ReportingStats($this->opdUser, $this->period))->progress())->toBe([
        'verified' => 1, 'submitted' => 1, 'revision' => 1, 'draft' => 1, 'missing' => 1, 'total' => 5,
    ]);
});

test('progres admin seluruh distrik: kelompok multi-OPD dihitung sekali', function () {
    expect((new ReportingStats($this->admin, $this->period))->progress())->toBe([
        'verified' => 2, 'submitted' => 1, 'revision' => 1, 'draft' => 1, 'missing' => 2, 'total' => 7,
    ]);
});

test('progres per OPD menghitung kelompok di setiap OPD-nya, plus baris tanpa OPD', function () {
    $rows = (new ReportingStats($this->admin, $this->period))->progressPerOpd();

    expect($rows->map(fn ($row) => [$row['opd']?->name, $row['counts']['total'], $row['counts']['verified'], $row['counts']['missing']])->all())->toBe([
        ['OPD A', 5, 1, 1],
        ['OPD B', 2, 2, 0],
        [null, 1, 0, 1],
    ]);
});

test('daftar belum mengajukan: belum dibuat dan draf saja', function () {
    $rows = (new ReportingStats($this->opdUser, $this->period))->notSubmitted();

    expect($rows->map(fn ($row) => [$row['organization']->name, $row['report']?->status])->all())->toBe([
        ['Empat', ReportStatus::Draft],
        ['Lima', null],
    ]);
});

test('masalah terbanyak dihitung per kelompok unik, hanya dari laporan diajukan/terverifikasi milik binaan', function () {
    $this->r1->issues()->createMany([
        ['kind' => IssueKind::Problem, 'category' => 'modal'],
        ['kind' => IssueKind::Problem, 'category' => 'modal'],
        ['kind' => IssueKind::Support, 'category' => 'pelatihan'],
    ]);
    $this->r2->issues()->create(['kind' => IssueKind::Problem, 'category' => 'modal']);
    $this->r2->issues()->create(['kind' => IssueKind::Problem, 'category' => 'pemasaran']);
    $this->r3->issues()->create(['kind' => IssueKind::Problem, 'category' => 'pemasaran']);
    $this->r8->issues()->create(['kind' => IssueKind::Problem, 'category' => 'pemasaran']);

    $top = (new ReportingStats($this->opdUser, $this->period))->topIssues();

    expect($top['problem']->map(fn ($row) => [$row['label'], $row['organizations']])->all())->toBe([['Modal', 2], ['Pemasaran', 1]])
        ->and($top['support']->map(fn ($row) => [$row['label'], $row['organizations']])->all())->toBe([['Pelatihan', 1]]);
});

test('ekonomi hanya dari laporan terverifikasi; NULL tidak dihitung sebagai nol', function () {
    $economy = (new DistrictStats)->economy($this->period);

    expect($economy['reports'])->toBe(2)
        ->and($economy['obligated'])->toBe(7)
        ->and($economy['totals']['income_sales'])->toBe(['sum' => 300000, 'filled' => 2])
        ->and($economy['totals']['income_other'])->toBe(['sum' => 0, 'filled' => 1])
        ->and($economy['totals']['costs'])->toBe(['sum' => 90000, 'filled' => 2])
        // Laba kelompok Satu NULL karena pendapatan lain kosong.
        ->and($economy['totals']['profit'])->toBe(['sum' => 150000, 'filled' => 1]);

    expect((new DistrictStats)->economy(CarbonImmutable::parse('2026-08-01'))['totals']['income_sales'])->toBe(['sum' => null, 'filled' => 0]);
});

test('produksi dikelompokkan per produk dan satuan yang dirapikan; satuan berbeda tidak dijumlahkan', function () {
    $this->r1->items()->create(['product_name' => 'Kakao kering', 'production_qty' => 10, 'production_unit' => 'kg']);
    $this->r8->items()->create(['product_name' => ' kakao KERING ', 'production_qty' => 5.5, 'production_unit' => 'KG ']);
    $this->r8->items()->create(['product_name' => 'Kakao kering', 'production_qty' => 2, 'production_unit' => 'karung']);
    $this->r8->items()->create(['product_name' => 'Pinang', 'production_qty' => null, 'production_unit' => null]);
    $this->r2->items()->create(['product_name' => 'Kakao kering', 'production_qty' => 99, 'production_unit' => 'kg']);

    $rows = (new Statistics($this->admin, [], $this->period, $this->period))->production();

    expect($rows->map(fn ($row) => [mb_strtolower($row['product']), $row['unit'], $row['quantity'] === null ? null : (float) $row['quantity'], $row['organizations']])->all())->toBe([
        ['kakao kering', 'kg', 15.5, 2],
        ['kakao kering', 'karung', 2.0, 1],
        ['pinang', null, null, 1],
    ]);
});

test('jumlah kelompok per status, jenis, dan bidang; daftar perhatian', function () {
    User::factory()->kelompok($this->o1)->create();
    ReportReview::forceCreate(['monthly_report_id' => $this->r2->id, 'action' => ReviewAction::Submitted, 'created_at' => '2026-09-30 10:00:00']);

    $stats = new DistrictStats;
    $organizations = $stats->organizations();

    expect($organizations['total'])->toBe(9)
        ->and($organizations['statuses']->all())->toBe(['active' => 7, 'inactive' => 1, 'closed' => 1])
        ->and($organizations['types']->sum('value'))->toBe(9)
        ->and($stats->attention(User::factory()->admin()->create()))->toMatchArray(['without_opd' => 1, 'admin_queue' => 0, 'waiting' => 1, 'active_without_account' => 6]);
});

test('pilihan bulan: bulan lalu sebagai default; nilai tidak sah atau di luar rentang kembali ke default', function () {
    $earliest = CarbonImmutable::parse('2026-07-01');

    expect(ReportingPeriod::due()->format('Y-m'))->toBe('2026-09')
        ->and(ReportingPeriod::resolve('2026-08', $earliest)->format('Y-m'))->toBe('2026-08')
        ->and(ReportingPeriod::resolve('2026-10', $earliest)->format('Y-m'))->toBe('2026-09')
        ->and(ReportingPeriod::resolve('2026-06', $earliest)->format('Y-m'))->toBe('2026-09')
        ->and(ReportingPeriod::resolve('bukan', $earliest)->format('Y-m'))->toBe('2026-09')
        ->and(ReportingPeriod::resolve(['2026-08'], $earliest)->format('Y-m'))->toBe('2026-09')
        ->and(array_keys(ReportingPeriod::options($earliest)))->toBe(['2026-09', '2026-08', '2026-07']);
});

test('jarak ke tenggat dalam kalimat', function () {
    expect(ReportingPeriod::deadlineDistance(CarbonImmutable::parse('2026-10-10 23:59:59')))->toBe('hari ini')
        ->and(ReportingPeriod::deadlineDistance(CarbonImmutable::parse('2026-10-16 23:59:59')))->toBe('6 hari lagi')
        ->and(ReportingPeriod::deadlineDistance(CarbonImmutable::parse('2026-10-07 23:59:59')))->toBe('lewat 3 hari');
});

test('ringkasan kelompok: bulan wajib lapor, bulan sebelumnya yang belum diajukan, kelengkapan, riwayat', function () {
    MonthlyReport::factory()->for($this->o5)->status(ReportStatus::Verified)->create(['period' => '2026-07-01', 'submitted_at' => now()]);
    MonthlyReport::factory()->for($this->o5)->create(['period' => '2026-10-01']);
    Product::factory()->for($this->o5)->create(['photo_path' => null]);
    $this->o5->update(['description' => 'Kelompok contoh', 'legal_status' => LegalStatus::InProcess]);

    $summary = new KelompokSummary($this->o5->fresh());

    expect($summary->due()['period']->format('Y-m'))->toBe('2026-09')
        ->and($summary->due()['report'])->toBeNull()
        ->and($summary->due()['distance'])->toBe('hari ini')
        ->and($summary->due()['earlierOutstanding'])->toBe(1)
        ->and($summary->current()['report'])->not->toBeNull()
        ->and(collect($summary->profileChecklist())->pluck('done', 'label')->all())->toBe([
            'Deskripsi kelompok' => true, 'Minimal satu produk' => true, 'Foto produk' => false, 'Status legalitas' => true,
        ])
        ->and($summary->history()->map(fn ($row) => [$row['period']->format('Y-m'), $row['report']?->status])->all())->toBe([
            ['2026-09', null], ['2026-08', null], ['2026-07', ReportStatus::Verified],
        ]);
});

test('kelompok yang kewajibannya belum dimulai tidak punya kartu bulan wajib lapor', function () {
    $new = Organization::factory()->create(['reporting_starts_on' => '2026-10-01']);

    expect((new KelompokSummary($new))->due())->toBeNull();
});
