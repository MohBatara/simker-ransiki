<?php

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Support\Dashboards\Statistics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/*
 * Kartu "Produksi per produk" (dasbor Admin/OPD, tab Ekonomi): urutan menurut jumlah kelompok lalu nama (tidak pernah
 * membandingkan angka antarsatuan), nama kelompok, dan perubahan setara dibanding bulan lalu.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'Asia/Jayapura'));
    $this->period = CarbonImmutable::parse('2026-09-01');
    $this->opd = Opd::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->orgs = collect(['Kelompok Alfa', 'Kelompok Beta', 'Kelompok Gama', 'Kelompok Delta'])
        ->mapWithKeys(function (string $name) {
            $organization = Organization::factory()->create(['name' => $name, 'reporting_starts_on' => '2026-01-01']);
            $organization->opds()->attach($this->opd);

            return [$name => $organization];
        });
});

afterEach(fn () => Carbon::setTestNow());

/** Laporan satu kelompok satu bulan berisi baris [produk, jumlah|null, satuan]. */
function produce(Organization $organization, string $period, array $items, ReportStatus $status = ReportStatus::Verified): void
{
    $report = MonthlyReport::factory()->for($organization)->status($status)->create(['period' => $period, 'submitted_at' => '2026-10-02 10:00']);

    foreach ($items as [$name, $quantity, $unit]) {
        $report->items()->create(['product_name' => $name, 'production_qty' => $quantity, 'production_unit' => $unit]);
    }
}

function productionRows(User $user, CarbonImmutable $period): Collection
{
    return (new Statistics($user, [], $period, $period))->production(5);
}

test('urut jumlah kelompok yang memproduksi lalu nama produk, bukan jumlah antarsatuan', function () {
    // Beras 5.000 kg dari 1 kelompok; telur 30 buah dari 3 kelompok; ayam 20 ekor dari 2 kelompok; abon dari 2 kelompok.
    produce($this->orgs['Kelompok Alfa'], '2026-09-01', [['Beras', 5000, 'kg'], ['Telur', 10, 'buah'], ['Ayam', 10, 'ekor']]);
    produce($this->orgs['Kelompok Beta'], '2026-09-01', [['Telur', 10, 'buah'], ['Ayam', 10, 'ekor'], ['Abon', 3, 'bungkus']]);
    produce($this->orgs['Kelompok Gama'], '2026-09-01', [['Telur', 10, 'buah'], ['abon ', 4, 'Bungkus']]);
    // Draf/menunggu tidak dihitung.
    produce($this->orgs['Kelompok Delta'], '2026-09-01', [['Beras', 9, 'kg']], ReportStatus::Submitted);

    $rows = productionRows($this->admin, $this->period);

    expect($rows->map(fn ($row) => [$row['product'], $row['unit'], $row['organizations']])->all())->toBe([
        ['Telur', 'buah', 3],
        ['Abon', 'bungkus', 2],
        ['Ayam', 'ekor', 2],
        ['Beras', 'kg', 1],
    ])
        ->and($rows[1]['quantity'])->toBe(7.0)
        ->and($rows[0]['organization_names'])->toBe(['Kelompok Alfa', 'Kelompok Beta', 'Kelompok Gama'])
        ->and($rows[3]['organization_names'])->toBe(['Kelompok Alfa']);
});

test('perbandingan setara: hanya kelompok yang mengisi produk dan satuan yang sama di kedua bulan', function () {
    // Agustus: Alfa 100 kg, Beta 50 kg, Gama 30 karung (satuan lain, tidak ikut).
    produce($this->orgs['Kelompok Alfa'], '2026-08-01', [['Kakao', 100, 'kg']]);
    produce($this->orgs['Kelompok Beta'], '2026-08-01', [['Kakao', 50, 'kg']]);
    produce($this->orgs['Kelompok Gama'], '2026-08-01', [['Kakao', 30, 'karung']]);
    // September: Alfa 120 kg, Beta tidak mengisi jumlah, Delta baru 500 kg (tidak ada di Agustus, tidak ikut dibandingkan).
    produce($this->orgs['Kelompok Alfa'], '2026-09-01', [['Kakao', 120, 'kg']]);
    produce($this->orgs['Kelompok Beta'], '2026-09-01', [['Kakao', null, 'kg']]);
    produce($this->orgs['Kelompok Delta'], '2026-09-01', [['Kakao', 500, 'kg'], ['Madu', 5, 'liter']]);
    // Gama September: karung, hanya dibanding karung Agustus (30 → 15 = turun 50%).
    produce($this->orgs['Kelompok Gama'], '2026-09-01', [['Kakao', 15, 'karung']]);

    $rows = productionRows($this->admin, $this->period)->keyBy(fn ($row) => $row['product'].'|'.$row['unit']);

    // Kakao kg: hanya Alfa yang mengisi di kedua bulan: 100 → 120 = naik 20%. Jumlah bulan ini tetap 620 dari 2 pengisi.
    expect($rows['Kakao|kg']['quantity'])->toBe(620.0)
        ->and($rows['Kakao|kg']['filled'])->toBe(2)
        ->and($rows['Kakao|kg']['organizations'])->toBe(3)
        ->and($rows['Kakao|kg']['change'])->toBe(['status' => 'compared', 'percent' => 20.0, 'matched' => 1, 'previous' => 'Agustus 2026'])
        ->and($rows['Kakao|karung']['change'])->toMatchArray(['status' => 'compared', 'percent' => -50.0, 'matched' => 1])
        // Tidak ada kelompok yang melaporkan madu di Agustus: "Baru".
        ->and($rows['Madu|liter']['change'])->toMatchArray(['status' => 'new', 'percent' => null]);
});

test('belum bisa dibandingkan bila produk ada bulan lalu tetapi tidak ada kelompok yang mengisi di kedua bulan', function () {
    produce($this->orgs['Kelompok Alfa'], '2026-08-01', [['Sagu', 40, 'kg']]);
    produce($this->orgs['Kelompok Beta'], '2026-09-01', [['Sagu', 60, 'kg']]);
    // Bulan lalu belum terverifikasi: tidak dipakai sebagai pembanding.
    produce($this->orgs['Kelompok Gama'], '2026-08-01', [['Pinang', 10, 'kg']], ReportStatus::Submitted);
    produce($this->orgs['Kelompok Gama'], '2026-09-01', [['Pinang', 12, 'kg']]);

    $rows = productionRows($this->admin, $this->period)->keyBy('product');

    expect($rows['Sagu']['change'])->toMatchArray(['status' => 'incomparable', 'percent' => null, 'matched' => 0])
        ->and($rows['Pinang']['change']['status'])->toBe('new');
});

test('OPD hanya menghitung kelompok binaannya, juga untuk pembanding', function () {
    $other = Organization::factory()->create(['name' => 'Kelompok Lain', 'reporting_starts_on' => '2026-01-01']);
    produce($other, '2026-08-01', [['Kakao', 1000, 'kg']]);
    produce($other, '2026-09-01', [['Kakao', 10, 'kg']]);
    produce($this->orgs['Kelompok Alfa'], '2026-08-01', [['Kakao', 10, 'kg']]);
    produce($this->orgs['Kelompok Alfa'], '2026-09-01', [['Kakao', 15, 'kg']]);

    $row = productionRows(User::factory()->opd($this->opd)->create(), $this->period)->first();

    expect($row['organization_names'])->toBe(['Kelompok Alfa'])
        ->and($row['quantity'])->toBe(15.0)
        ->and($row['change'])->toMatchArray(['status' => 'compared', 'percent' => 50.0]);
});

test('kartu dasbor: subjudul baru, nama kelompok tunggal, "N kelompok" dengan daftar di tooltip, panah dan warna', function () {
    produce($this->orgs['Kelompok Alfa'], '2026-08-01', [['Telur', 10, 'buah'], ['Ayam', 20, 'ekor']]);
    produce($this->orgs['Kelompok Beta'], '2026-08-01', [['Telur', 10, 'buah']]);
    produce($this->orgs['Kelompok Alfa'], '2026-09-01', [['Telur', 15, 'buah'], ['Ayam', 10, 'ekor'], ['Madu', 2, 'liter']]);
    produce($this->orgs['Kelompok Beta'], '2026-09-01', [['Telur', 15, 'buah']]);

    $this->actingAs($this->admin)->get('/admin?periode=2026-09')->assertOk()
        ->assertSee('Lima produk yang paling banyak diproduksi kelompok. Satuan berbeda tidak dijumlahkan.')
        ->assertSee('Vs Agustus')->assertDontSee('Dibanding bulan lalu')
        ->assertSee('2 kelompok')
        ->assertSee('title="Kelompok Alfa, Kelompok Beta"', false)
        ->assertSee('data-production-change="up"', false)
        ->assertSee('data-production-change="down"', false)
        ->assertSee('data-production-change="new"', false)
        ->assertSee('title="Dibanding Agustus 2026, dari 2 kelompok yang sama"', false)
        ->assertSee('lg:col-span-7', false);

    $this->get('/admin/dasbor/ekonomi?dari=2026-09&sampai=2026-09')->assertOk()
        ->assertSee('Dibanding periode sebelumnya')
        ->assertSee('data-production-change="up"', false);
});
