<?php

use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DemoSeeder;
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

test('data contoh sesuai CLAUDE.md bagian 10', function () {
    $this->seed(DemoSeeder::class);

    expect(Opd::count())->toBe(4)
        ->and(Organization::count())->toBe(25)
        ->and(Organization::where('status', OrganizationStatus::Closed)->count())->toBe(1)
        ->and(Organization::where('status', OrganizationStatus::Inactive)->count())->toBeGreaterThan(1)
        ->and(MonthlyReport::distinct()->count('period'))->toBe(3)
        ->and(MonthlyReport::where('no_activity', true)->exists())->toBeTrue()
        ->and(MonthlyReport::where('no_activity', false)->whereNull('profit')->exists())->toBeTrue();

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
