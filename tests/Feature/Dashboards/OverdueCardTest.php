<?php

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Carbon;

/*
 * Kartu "Terlambat melapor" dasbor Admin/OPD (shared.dashboards.not-submitted) dan format nomor HP untuk tampilan.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'Asia/Jayapura'));
    $this->opd = Opd::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->make = function (string $name, array $attributes = [], ?ReportStatus $status = null) {
        $organization = Organization::factory()->create(['name' => $name, 'reporting_starts_on' => '2026-07-01', ...$attributes]);
        $organization->opds()->attach($this->opd);
        if ($status) {
            MonthlyReport::factory()->for($organization)->status($status)->create([
                'period' => '2026-09-01', 'submitted_at' => $status === ReportStatus::Draft ? null : '2026-10-03 10:00',
            ]);
        }

        return $organization;
    };
});

afterEach(fn () => Carbon::setTestNow());

test('nomor HP tampil dengan spasi, data tersimpan tidak berubah', function () {
    expect(Format::phone('0812-0000-1005'))->toBe('0812 0000 1005')
        ->and(Format::phone('081200001005'))->toBe('0812 0000 1005')
        ->and(Format::phone('0812.345.678'))->toBe('0812 3456 78')
        ->and(Format::phone('+62 812-0000-1005'))->toBe('+62 812 0000 1005')
        ->and(Format::phone('(0986) 211-234'))->toBe('(0986) 211 234')
        ->and(Format::phone(null))->toBeNull()
        ->and(Format::phone('  '))->toBeNull()
        ->and(Format::telUrl('0812-0000-1005'))->toBe('tel:081200001005')
        ->and(Format::telUrl('+62 812 0000 1005'))->toBe('tel:+6281200001005');

    $organization = ($this->make)('KUB Telepon', ['phone' => '0812-0000-1005', 'leader_name' => 'La Ode Hamid']);
    expect($organization->fresh()->phone)->toBe('0812-0000-1005');
});

test('baris bertumpuk: nama, satu pil keadaan tanpa "Terlambat", ketua dan nomor HP bertautan tel:, tombol salin', function () {
    ($this->make)('KUB Draf Lama', ['leader_name' => 'La Ode Hamid', 'phone' => '0812-0000-1005'], ReportStatus::Draft);
    ($this->make)('KUB Belum Isi', ['leader_name' => 'Siti Aminah']);
    ($this->make)('KUB Sudah Lapor', [], ReportStatus::Submitted);

    $html = $this->actingAs($this->admin)->get('/admin?periode=2026-09')->assertOk()
        ->assertSee('Terlambat melapor September 2026')
        ->assertSee('Draf, belum diajukan')
        ->assertSee('Belum diisi')
        ->assertSee('Ketua: La Ode Hamid')
        ->assertSee('href="tel:081200001005"', false)
        ->assertSee('0812 0000 1005')
        ->assertDontSee('0812-0000-1005')
        ->assertSee('Salin pesan pengingat')
        ->getContent();

    preg_match('#<ul[^>]*data-not-submitted.*?</ul>#s', $html, $list);
    expect($list[0])->not->toContain('>Terlambat</span>')
        ->and($list[0])->not->toContain('KUB Sudah Lapor')
        ->and(substr_count($list[0], '<li'))->toBe(2)
        // Kartu tidak dipaksa setinggi kartu Produksi di sebelahnya.
        ->and($html)->toContain('lg:self-start');
});

test('maksimal 5 kelompok, lalu "Lihat semua (N)" ke daftar tersaring terlambat', function () {
    foreach (range(1, 7) as $n) {
        ($this->make)("KUB Terlambat {$n}");
    }

    $html = $this->actingAs($this->admin)->get('/admin?periode=2026-09')->assertOk()
        ->assertSee('Lihat semua (7)')
        ->assertSee(e(route('admin.organizations.index', ['laporan' => 'terlambat', 'periode' => '2026-09'])), false)
        ->getContent();

    preg_match('#<ul[^>]*data-not-submitted.*?</ul>#s', $html, $list);
    expect(substr_count($list[0], '<li'))->toBe(5);
});

test('tidak ada yang terlambat: centang dan "Semua kelompok sudah melapor tepat waktu"', function () {
    ($this->make)('KUB Rajin', [], ReportStatus::Verified);

    $this->actingAs(User::factory()->opd($this->opd)->create())->get('/opd?periode=2026-09')->assertOk()
        ->assertSee('data-all-submitted', false)
        ->assertSee('Semua kelompok sudah melapor tepat waktu.');
});
