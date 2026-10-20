<?php

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
 * Kartu "Pelaporan per OPD" (Dasbor Admin): baris per OPD urut persen terverifikasi terendah, bilah bertumpuk bertooltip,
 * ringkasan tanpa angka nol, persen + "X dari Y kelompok", baris menaut ke daftar Laporan OPD + periode, dan baris
 * "Seluruh distrik" yang menghitung setiap kelompok sekali. Angka tetap dari ReportingStats.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'Asia/Jayapura'));
    $this->admin = User::factory()->admin()->create();
    $this->dkpp = Opd::factory()->create(['name' => 'Dinas Ketahanan Pangan dan Pertanian', 'short_name' => 'DKPP']);
    $this->ikan = Opd::factory()->create(['name' => 'Dinas Perikanan', 'short_name' => 'Perikanan']);

    $this->make = function (array $opds, ?ReportStatus $status) {
        $organization = Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);
        $organization->opds()->attach(collect($opds)->pluck('id'));
        if ($status) {
            MonthlyReport::factory()->for($organization)->status($status)->create([
                'period' => '2026-09-01', 'submitted_at' => $status === ReportStatus::Draft ? null : '2026-10-03 10:00',
            ]);
        }

        return $organization;
    };

    // DKPP: 1 terverifikasi, 3 menunggu, 1 revisi (20%). Perikanan: 2 terverifikasi, 1 belum (67%).
    // Satu kelompok terverifikasi dibina keduanya: dihitung di kedua OPD, sekali di Seluruh distrik.
    ($this->make)([$this->dkpp, $this->ikan], ReportStatus::Verified);
    foreach (range(1, 3) as $i) {
        ($this->make)([$this->dkpp], ReportStatus::Submitted);
    }
    ($this->make)([$this->dkpp], ReportStatus::Revision);
    ($this->make)([$this->ikan], ReportStatus::Verified);
    ($this->make)([$this->ikan], null);
});

afterEach(fn () => Carbon::setTestNow());

function opdCard(mixed $test): string
{
    $html = $test->get('/admin?periode=2026-09')->assertOk()->getContent();
    preg_match('#<ul class="border-t border-line" data-opd-rows>.*?</ul>#s', $html, $card);

    return $card[0];
}

test('baris urut persen terverifikasi terendah, Seluruh distrik terakhir dengan kelompok dihitung sekali', function () {
    $this->actingAs($this->admin);
    $card = opdCard($this);

    preg_match_all('#data-opd-row="([^"]+)"#', $card, $rows);
    expect($rows[1])->toBe(['DKPP', 'Perikanan', 'Seluruh distrik']);

    $text = preg_replace('/\s+/', ' ', strip_tags($card));
    expect($text)->toContain('DKPP Dinas Ketahanan Pangan dan Pertanian')
        ->toContain('1 terverifikasi · 3 menunggu · 1 revisi 20% 1 dari 5 kelompok')
        ->toContain('2 terverifikasi · 1 belum diajukan 67% 2 dari 3 kelompok')
        // 7 kelompok unik (kelompok bersama tidak ganda): 2 terverifikasi, 3 menunggu, 1 revisi, 1 belum.
        ->toContain('Seluruh distrik Setiap kelompok dihitung sekali 2 terverifikasi · 3 menunggu · 1 revisi · 1 belum diajukan 29% 2 dari 7 kelompok')
        // Angka nol tidak disebut.
        ->not->toContain('0 revisi')->not->toContain('0 menunggu');
});

test('segmen bertooltip, baris menaut ke daftar Laporan OPD + periode, legenda empat status', function () {
    $this->actingAs($this->admin);
    $card = opdCard($this);

    expect($card)->toContain('title="Menunggu verifikasi: 3 kelompok"')
        ->toContain('title="Perlu revisi: 1 kelompok"')
        ->toContain('title="Belum diajukan: 1 kelompok"')
        ->toContain('href="'.e(route('admin.reports.index', ['opd' => $this->dkpp->id, 'period' => '2026-09'])).'"')
        ->toContain('class="border-b border-line-soft last:border-b-0 bg-brand-50" data-opd-row="Seluruh distrik"');

    $this->get('/admin?periode=2026-09')
        ->assertSee('September 2026 · kelompok dengan beberapa OPD dihitung di setiap OPD')
        ->assertSeeInOrder(['data-opd-legend', 'Terverifikasi', 'Menunggu verifikasi', 'Perlu revisi', 'Belum diajukan'], false);
});
