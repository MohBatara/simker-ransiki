<?php

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;

afterEach(fn () => Carbon::setTestNow());

test('tenggat tinggal 3 hari atau kurang ditandai wait dan tebal; lewat tenggat memakai fix', function (string $now, string $classes, string $text) {
    Carbon::setTestNow(Carbon::parse($now, 'Asia/Jayapura'));
    $organization = Organization::factory()->create(['name' => 'KUB Contoh Tenggat', 'reporting_starts_on' => '2026-07-01']);

    $html = $this->actingAs(User::factory()->kelompok($organization)->create())->get('/kelompok')->assertOk()->getContent();

    expect($html)->toMatch('/<p class="'.preg_quote($classes, '/').'">\s*Tenggat 10 Oktober 2026 · '.preg_quote($text, '/').'/');
})->with([
    '6 hari' => ['2026-10-04 09:00:00', 'text-ink-soft', '6 hari lagi'],
    '3 hari' => ['2026-10-07 09:00:00', 'font-semibold text-wait', '3 hari lagi'],
    'hari ini' => ['2026-10-10 09:00:00', 'font-semibold text-wait', 'hari ini'],
    'lewat' => ['2026-10-12 09:00:00', 'text-fix', 'lewat 2 hari'],
]);

test('dasbor kelompok memakai nama kelompok sebagai judul dengan keterangan Dasbor di atasnya', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-04 09:00:00', 'Asia/Jayapura'));
    $organization = Organization::factory()->create(['name' => 'KUB Judul Contoh', 'reporting_starts_on' => '2026-07-01']);

    $this->actingAs(User::factory()->kelompok($organization)->create())->get('/kelompok')
        ->assertOk()
        ->assertSeeInOrder(['>Dasbor</p>', '>KUB Judul Contoh</h1>'], false)
        ->assertSee('<title>KUB Judul Contoh', false);
});

test('judul kolom angka dan selnya memakai padding dan perataan yang sama', function () {
    $html = Blade::render('<x-table><x-slot:head><x-table.heading numeric>Laba</x-table.heading></x-slot:head><x-table.row><x-table.cell label="Laba" numeric>1</x-table.cell></x-table.row></x-table>');

    preg_match('/<th scope="col"[^>]*class="([^"]*)"/', $html, $th);
    preg_match('/<td[^>]*class="([^"]*)"/', $html, $td);

    expect($th[1])->toContain('pl-4 pr-8 text-right')->not->toContain('px-4')
        ->and($td[1])->toContain('pl-4 pr-4 md:pr-8')->toContain('md:text-right')->not->toMatch('/(^|\s)px-4(\s|$)/');
});

test('perlu dikerjakan: hanya tugas yang ada (tanpa baris angka 0); per OPD di dalam kartu', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jayapura'));
    $organization = Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);
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
