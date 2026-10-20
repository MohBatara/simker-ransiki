<?php

use App\Enums\ProfileStatus;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;

afterEach(fn () => Carbon::setTestNow());

test('dasbor kelompok: judul Dasbor lalu banner sapaan dengan nama kelompok', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-04 09:00:00', 'Asia/Jayapura'));
    $organization = Organization::factory()->create(['name' => 'KUB Judul Contoh', 'reporting_starts_on' => '2026-07-01']);

    $this->actingAs(User::factory()->kelompok($organization)->create())->get('/kelompok')
        ->assertOk()
        ->assertSeeInOrder(['>Dasbor</h1>', 'KUB Judul Contoh', 'Selamat pagi'], false)
        ->assertSee('<title>Dasbor', false);
});

test('judul kolom angka dan selnya memakai padding dan perataan yang sama', function () {
    $html = Blade::render('<x-admin.table><x-slot:head><x-admin.table.heading numeric>Laba</x-admin.table.heading></x-slot:head><x-admin.table.row><x-admin.table.cell label="Laba" numeric>1</x-admin.table.cell></x-admin.table.row></x-admin.table>');

    preg_match('/<th scope="col"[^>]*class="([^"]*)"/', $html, $th);
    preg_match('/<td[^>]*class="([^"]*)"/', $html, $td);

    expect($th[1])->toContain('pl-4 pr-8 text-right')->not->toContain('px-4')
        ->and($td[1])->toContain('pl-4 pr-4 md:pr-8')->toContain('md:text-right')->not->toMatch('/(^|\s)px-4(\s|$)/');
});

test('perlu dikerjakan: hanya tugas yang ada (tanpa baris angka 0); per OPD di dalam kartu', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jayapura'));
    $organization = Organization::factory()->create(['reporting_starts_on' => '2026-07-01', 'profile_status' => ProfileStatus::Verified]);
    $organization->opds()->attach(Opd::factory()->create());
    User::factory()->kelompok($organization)->create();
    MonthlyReport::factory()->for($organization)->status(ReportStatus::Verified)->create(['period' => '2026-09-01', 'submitted_at' => now()]);

    $this->actingAs(User::factory()->admin()->create())->get('/admin')
        ->assertOk()
        ->assertSee('Perlu dikerjakan')
        ->assertSee('Tidak ada yang perlu dikerjakan sekarang.')
        ->assertDontSee('belum punya OPD pembina')
        ->assertDontSee('belum punya akun')
        ->assertSee('Pelaporan per OPD');
});
