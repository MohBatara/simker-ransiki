<?php

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
 * Kartu "Pelaporan per OPD" (Dasbor Admin): satu warna (brand) dua tingkat, rincian netral dengan clay hanya untuk
 * "perlu revisi" dan "belum diajukan", persen clay bila < 50%, urut persen terverifikasi terendah, baris menaut ke daftar
 * Laporan OPD + periode, dan "Seluruh distrik" yang menghitung setiap kelompok sekali. Angka tetap dari ReportingStats.
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

/** @return array{0: string, 1: array<string, string>} isi kartu + HTML per baris (kunci = nama baris) */
function opdCard(mixed $test): array
{
    $html = $test->get('/admin?periode=2026-09')->assertOk()->getContent();
    preg_match('#<ul class="border-t border-line" data-opd-rows>.*?</ul>#s', $html, $card);
    preg_match_all('#<li[^>]*data-opd-row="([^"]+)">(.*?)</li>#s', $card[0], $rows, PREG_SET_ORDER);

    return [$card[0], collect($rows)->mapWithKeys(fn ($row) => [$row[1] => $row[0]])->all()];
}

test('baris urut persen terverifikasi terendah, Seluruh distrik terakhir dengan kelompok dihitung sekali', function () {
    $this->actingAs($this->admin);
    [, $rows] = opdCard($this);

    expect(array_keys($rows))->toBe(['DKPP', 'Perikanan', 'Seluruh distrik']);

    $text = fn (string $row) => preg_replace('/\s+/', ' ', trim(strip_tags($rows[$row])));
    expect($text('DKPP'))->toBe('DKPP Dinas Ketahanan Pangan dan Pertanian 1 terverifikasi · 3 menunggu · 1 perlu revisi 20% 1 dari 5 kelompok')
        ->and($text('Perikanan'))->toBe('Perikanan Dinas Perikanan 2 terverifikasi · 1 belum diajukan 67% 2 dari 3 kelompok')
        // 7 kelompok unik (kelompok bersama tidak ganda): 2 terverifikasi, 3 menunggu, 1 revisi, 1 belum.
        ->and($text('Seluruh distrik'))->toBe('Seluruh distrik Setiap kelompok dihitung sekali 2 terverifikasi · 3 menunggu · 1 perlu revisi · 1 belum diajukan 29% 2 dari 7 kelompok');
});

test('bilah satu warna dua tingkat tanpa pola, clay hanya untuk yang perlu perhatian dan persen di bawah 50%', function () {
    $this->actingAs($this->admin);
    [$card, $rows] = opdCard($this);

    // Terverifikasi brand, menunggu + perlu revisi brand 30%, sisanya track abu polos.
    expect($rows['DKPP'])->toContain('class="h-full bg-brand" style="width: 20%" title="Terverifikasi: 1 kelompok"')
        ->toContain('class="h-full bg-brand/30" style="width: 80%" title="Sudah diajukan, belum terverifikasi: 4 kelompok"')
        ->toContain('h-2 w-full overflow-hidden rounded-full bg-[#ECEEEA]')
        ->and($card)->not->toContain('repeating-linear-gradient')->not->toContain('#C9962E')->not->toContain('bg-brand-50');

    // Clay: "perlu revisi" dan "belum diajukan" saja; persen 20% clay, 67% ink.
    preg_match_all('#<span class="text-\[\#A63A2B\]"\s+data-attention\s*>([^<]+)</span>#', $card, $attention);
    expect($attention[1])->toBe(['1 perlu revisi', '1 belum diajukan', '1 perlu revisi', '1 belum diajukan'])
        ->and($rows['DKPP'])->toMatch('#text-\[\#A63A2B\]" data-percent>20%#')
        ->and($rows['Perikanan'])->toMatch('#text-ink" data-percent>67%#');

    // Seluruh distrik: garis pemisah tebal, tanpa latar berwarna.
    expect($rows['Seluruh distrik'])->toContain('border-t-2 border-t-line-strong');
});

test('baris menaut ke daftar Laporan OPD + periode; keterangan satu baris menggantikan legenda empat warna', function () {
    $this->actingAs($this->admin);
    [$card] = opdCard($this);

    expect($card)->toContain('href="'.e(route('admin.reports.index', ['opd' => $this->dkpp->id, 'period' => '2026-09'])).'"');

    $this->get('/admin?periode=2026-09')
        ->assertSee('September 2026 · kelompok dengan beberapa OPD dihitung di setiap OPD')
        ->assertSee('Hijau tua: terverifikasi · Hijau muda: sudah diajukan, belum diperiksa');
});
