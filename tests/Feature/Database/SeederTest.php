<?php

use App\Enums\OrganizationStatus;
use App\Enums\ReportingState;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Product;
use App\Models\ReportIssue;
use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\DemoData;
use Database\Seeders\DemoSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

test('seeder utama di luar local hanya mengisi data master tanpa akun uji', function () {
    $this->seed();

    expect(InstitutionType::count())->toBe(9)
        ->and(BusinessSector::count())->toBe(7)
        ->and(User::count())->toBe(0)
        ->and(Organization::count())->toBe(0);
});

test('seeder master aman dijalankan ulang', function () {
    $this->seed();
    $this->seed();

    expect(InstitutionType::count())->toBe(9)
        ->and(BusinessSector::count())->toBe(7);
});

test('data demo sesuai CLAUDE.md bagian 10', function () {
    $this->seed(DemoSeeder::class);

    // Data ringkas untuk presentasi: 12 kelompok aktif, 15 produk, 4 berita, riwayat 6 bulan + bulan berjalan.
    expect(Opd::count())->toBe(4)
        ->and(Organization::count())->toBe(12)
        ->and(Organization::where('status', OrganizationStatus::Active)->count())->toBe(12)
        ->and(Organization::distinct()->count('institution_type_id'))->toBeGreaterThanOrEqual(6)
        ->and(Organization::distinct()->count('village_id'))->toBeGreaterThanOrEqual(6)
        ->and(Organization::where('name', 'like', '%Contoh%')->exists())->toBeFalse()
        ->and(Product::count())->toBe(15)
        ->and(Organization::withCount('products')->get()->every(fn ($organization) => in_array($organization->products_count, [1, 2])))->toBeTrue()
        ->and(Post::count())->toBe(4)
        ->and(ReportIssue::count())->toBeBetween(3, 4)
        ->and(MonthlyReport::distinct()->count('period'))->toBe(7)
        ->and(MonthlyReport::where('no_activity', true)->exists())->toBeTrue();

    foreach (ReportStatus::cases() as $status) {
        expect(MonthlyReport::where('status', $status)->exists())->toBeTrue();
    }

    $usernames = User::pluck('role', 'username')->map->value->all();
    expect($usernames)->toBe([
        'admin' => Role::Admin->value,
        'opd.pertanian' => Role::Opd->value,
        'opd.dpmptsp' => Role::Opd->value,
        'opd.dinkopdag' => Role::Opd->value,
        'opd.perikanan' => Role::Opd->value,
        'kelompok1' => Role::Kelompok->value,
        'kelompok2' => Role::Kelompok->value,
    ]);
});

test('data demo September 2026: 4 menunggu, 1 revisi milik kelompok1, 1 terlambat, sisanya terverifikasi; hitungan konsisten', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'Asia/Jayapura'));
    $this->seed(DemoSeeder::class);
    $period = Carbon::parse('2026-09-01');

    $states = Organization::obligatedFor($period)->with(['monthlyReports' => fn ($query) => $query->whereDate('period', $period)])->get()
        ->map(fn (Organization $organization) => ReportingState::for($organization->monthlyReports->first(), $period));
    $kelompok1 = User::where('username', 'kelompok1')->firstOrFail();
    $revision = MonthlyReport::whereDate('period', $period)->where('status', ReportStatus::Revision)->sole();

    expect($states->countBy(fn (ReportingState $state) => $state->value)->sortKeys()->all())->toBe(collect([
        ReportingState::Submitted->value => 4,
        ReportingState::Revision->value => 1,
        ReportingState::Overdue->value => 1,
        ReportingState::Verified->value => 6,
    ])->sortKeys()->all())
        ->and($revision->organization_id)->toBe($kelompok1->organization_id)
        ->and(Organization::overdueFor($period)->count())->toBe(1)
        ->and(MonthlyReport::verificationQueue(User::where('username', 'admin')->first())->count())->toBe(4)
        ->and($kelompok1->name)->toBe('Yakob Rumbiak')
        ->and(User::where('username', 'admin')->value('name'))->toBe('Maria Wonatorey')
        ->and(User::where('username', 'opd.pertanian')->value('name'))->toBe('Yosias Kafiar');

    // Badge menu, tab antrean, dan kotak dasbor memakai angka yang sama.
    foreach (['admin' => '/admin', 'opd.pertanian' => '/opd'] as $username => $url) {
        $user = User::where('username', $username)->firstOrFail();
        $queue = MonthlyReport::verificationQueue($user)->count();
        expect(Navigation::counts($user)['reports'] ?? null)->toBe($queue);
        $this->actingAs($user)->get($url)->assertOk();
    }
});

test('kelompok1 dan kelompok2 dibina OPD yang berbeda', function () {
    $this->seed(DemoSeeder::class);

    $opdsOf = fn (string $username) => DB::table('organization_opd')
        ->where('organization_id', User::where('username', $username)->value('organization_id'))
        ->pluck('opd_id');

    expect($opdsOf('kelompok1')->intersect($opdsOf('kelompok2')))->toBeEmpty();
});

test('minimal satu OPD tidak berbagi kelompok dengan OPD lain', function () {
    $this->seed(DemoSeeder::class);

    $isolated = Opd::all()->filter(function (Opd $opd) {
        $organizationIds = DB::table('organization_opd')->where('opd_id', $opd->id)->pluck('organization_id');

        return $organizationIds->isNotEmpty() && DB::table('organization_opd')
            ->whereIn('organization_id', $organizationIds)
            ->where('opd_id', '!=', $opd->id)
            ->doesntExist();
    });

    expect($isolated)->not->toBeEmpty();
});

test('tidak ada laporan untuk kelompok tutup atau sebelum wajib lapor', function () {
    $this->seed(DemoSeeder::class);

    $closedReports = MonthlyReport::whereHas('organization', fn ($query) => $query->where('status', OrganizationStatus::Closed))->count();
    $tooEarly = MonthlyReport::join('organizations', 'organizations.id', '=', 'monthly_reports.organization_id')
        ->whereColumn('monthly_reports.period', '<', 'organizations.reporting_starts_on')
        ->count();

    expect($closedReports)->toBe(0)->and($tooEarly)->toBe(0);
});

test('deskripsi dan foto jenis kelembagaan hanya diisi data demo: maks 50 karakter, foto untuk 3 jenis', function () {
    $this->seed();
    expect(InstitutionType::whereNotNull('description')->count())->toBe(0);

    $this->seed(DemoSeeder::class);
    $descriptions = InstitutionType::pluck('description', 'code');

    expect($descriptions)->toHaveCount(count(MasterDataSeeder::INSTITUTION_TYPES))
        ->each(fn ($text) => $text->not->toBeNull()->and(mb_strlen($text->value))->toBeLessThanOrEqual(50))
        ->and($descriptions->join(' '))->not->toMatch('/[\x{2013}\x{2014}]|\b(\w+)-\1\b/u')
        ->and(collect(DemoData::INSTITUTION_TYPE_PHOTOS)->keys()->every(fn ($code) => mb_strlen($descriptions[$code]) <= 30))->toBeTrue()
        // Foto demo hanya dibuat di local (DemoSeeder::photosEnabled); di sini cukup daftar dan filenya.
        ->and(DemoData::INSTITUTION_TYPE_PHOTOS)->toHaveCount(3)
        ->and(array_diff_key(DemoData::INSTITUTION_TYPE_PHOTOS, MasterDataSeeder::INSTITUTION_TYPES))->toBe([])
        ->and(collect(DemoData::INSTITUTION_TYPE_PHOTOS)->every(fn ($file) => is_file(database_path("seeders/photos/{$file}"))))->toBeTrue();
});
