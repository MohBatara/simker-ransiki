<?php

use App\Enums\IssueKind;
use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\ReportForm;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Jayapura'));

    $this->organization = Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);
    $this->organization->opds()->attach(Opd::factory()->create());
    $this->user = User::factory()->kelompok($this->organization)->create();
    $this->actingAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

function draftPayload(array $overrides = []): array
{
    return array_merge([
        'no_activity' => false,
        'activity_summary' => 'Produksi kopi bubuk',
        'activity_count' => '',
        'achievements' => '',
        'members_active' => '',
        'workforce_count' => '',
        'members_change' => '',
        'income_sales' => '',
        'income_other' => '',
        'costs' => '',
        'items' => [],
        'problems' => [],
        'supports' => [],
    ], $overrides);
}

test('daftar laporan menampilkan setiap bulan sejak wajib lapor, dengan status dan tenggat', function () {
    MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Verified)->create(['period' => '2026-08-01', 'submitted_at' => '2026-09-11 08:00:00']);
    MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);

    $this->get('/kelompok/laporan')
        ->assertOk()
        ->assertSeeInOrder(['Oktober 2026', 'September 2026', 'Agustus 2026', 'Juli 2026'])
        ->assertSee('Terverifikasi')
        ->assertSee('Draf')
        ->assertSee('Belum dibuat')
        ->assertSee('Diajukan terlambat')
        ->assertSee('10 November 2026')
        ->assertSee('Lewat tenggat')
        ->assertSee('Buat laporan')
        ->assertDontSee('Juni 2026');
});

test('membuat laporan membuka form draf; bulan yang sama tidak bisa dibuat dua kali', function () {
    $this->post('/kelompok/laporan', ['period' => '2026-09'])
        ->assertRedirect();

    $report = MonthlyReport::sole();
    expect($report->period->format('Y-m-d'))->toBe('2026-09-01')
        ->and($report->status)->toBe(ReportStatus::Draft);

    $this->post('/kelompok/laporan', ['period' => '2026-09'])
        ->assertRedirect("/kelompok/laporan/{$report->id}/ubah")
        ->assertSessionHas('status', 'Bulan ini sudah ada laporan. Laporan yang ada dibuka.');

    expect(MonthlyReport::count())->toBe(1);
});

test('database menolak dua laporan untuk kelompok dan bulan yang sama', function () {
    MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);

    expect(fn () => MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-15']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('laporan hanya bisa dibuat untuk bulan sejak wajib lapor sampai bulan ini', function (string $period) {
    $this->post('/kelompok/laporan', ['period' => $period])->assertSessionHasErrors('period');

    expect(MonthlyReport::count())->toBe(0);
})->with(['2026-06', '2026-11', 'bukan-bulan']);

test('kelompok tutup tidak bisa membuat laporan baru', function () {
    $this->organization->update(['status' => OrganizationStatus::Closed]);

    $this->get('/kelompok/laporan')->assertOk()->assertSee('sudah tutup')->assertDontSee('Buat laporan');
    $this->post('/kelompok/laporan', ['period' => '2026-09'])->assertForbidden();

    expect(MonthlyReport::count())->toBe(0);
});

test('kelompok tutup tetap bisa menyelesaikan draf yang ada', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);
    $this->organization->update(['status' => OrganizationStatus::Closed]);

    $this->get("/kelompok/laporan/{$report->id}/ubah")->assertOk();
    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload())->assertOk();
});

test('form laporan terbuka dengan isian tersimpan dan catatan revisi OPD di atas', function () {
    $report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Revision)->create([
        'period' => '2026-09-01', 'income_sales' => 1250000, 'submitted_at' => '2026-10-05 10:00:00',
    ]);
    $reviewer = User::factory()->opd($this->organization->opds->first())->create(['name' => 'Petugas Contoh']);
    $report->reviews()->create(['user_id' => $reviewer->id, 'action' => ReviewAction::RevisionRequested, 'note' => 'Lengkapi angka biaya.']);

    $this->get("/kelompok/laporan/{$report->id}/ubah")
        ->assertOk()
        ->assertSee('Perlu revisi')
        ->assertSee('Lengkapi angka biaya.')
        ->assertSee('Petugas Contoh')
        ->assertSee('1.250.000')
        ->assertSee('simker:laporan:'.$this->user->id.':'.$report->id);
});

test('simpan draf: kosong tetap NULL, nol tetap nol, angka format Indonesia terbaca, laba otomatis', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);

    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload([
        'members_active' => '0',
        'income_sales' => '1.250.000',
        'income_other' => '0',
        'costs' => 'Rp 250.000',
        'members_change' => '-2',
    ]))->assertOk()->assertJson(['profit' => 1000000]);

    $report->refresh();
    expect($report->members_active)->toBe(0)
        ->and($report->workforce_count)->toBeNull()
        ->and($report->activity_count)->toBeNull()
        ->and($report->members_change)->toBe(-2)
        ->and($report->income_sales)->toBe(1250000)
        ->and($report->income_other)->toBe(0)
        ->and($report->costs)->toBe(250000)
        ->and($report->profit)->toBe(1000000);

    // Satu komponen dikosongkan: laba kembali NULL, tidak dianggap nol.
    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload(['income_sales' => '1.250.000', 'income_other' => '', 'costs' => '250.000']))
        ->assertOk()->assertJson(['profit' => null]);

    expect($report->fresh()->income_other)->toBeNull()->and($report->fresh()->profit)->toBeNull();
});

test('simpan draf: laba bisa negatif', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);

    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload(['income_sales' => '100.000', 'income_other' => '0', 'costs' => '300.000']))
        ->assertOk()->assertJson(['profit' => -200000]);
});

test('simpan draf: jumlah produk dibaca dengan aturan koma dan titik', function (string $typed, string $stored) {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);

    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload([
        'items' => [['product_name' => 'Kopi bubuk', 'production_qty' => $typed, 'production_unit' => 'kg']],
    ]))->assertOk();

    expect($report->items()->sole()->production_qty)->toBe($stored);
})->with([
    ['12.5', '12.50'],
    ['12,5', '12.50'],
    ['1.250', '1250.00'],
    ['1.250.000', '1250000.00'],
    ['1.250,5', '1250.50'],
]);

test('simpan draf: angka yang tidak terbaca ditolak dengan pesan', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);

    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload([
        'income_sales' => 'satu juta',
        'items' => [['product_name' => 'Kopi', 'sales_qty' => '1,255', 'sales_unit' => 'kg']],
    ]))->assertUnprocessable()->assertJsonValidationErrors([
        'income_sales' => 'Angka tidak terbaca',
        'items.0.sales_qty' => 'paling banyak 2 angka di belakang koma',
    ]);
});

test('simpan draf mengganti baris produk dan masalah; nama produk terdaftar diambil dari datanya', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);
    $product = Product::factory()->for($this->organization)->create(['name' => 'Kakao fermentasi', 'unit' => 'kg']);

    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload([
        'items' => [
            ['product_id' => $product->id, 'product_name' => 'Nama palsu', 'production_qty' => '40', 'production_unit' => 'kg', 'sales_qty' => '3', 'sales_unit' => 'karung', 'revenue' => '1.200.000'],
            ['product_id' => null, 'product_name' => 'Keripik pisang', 'production_qty' => '', 'production_unit' => '', 'sales_qty' => '', 'sales_unit' => '', 'revenue' => ''],
            ['product_id' => null, 'product_name' => '', 'production_qty' => '', 'production_unit' => '', 'sales_qty' => '', 'sales_unit' => '', 'revenue' => ''],
        ],
        'problems' => [['category' => 'modal', 'description' => 'Modal terbatas'], ['category' => '', 'description' => '']],
        'supports' => [['category' => 'pelatihan', 'description' => '']],
    ]))->assertOk();

    $items = $report->items()->orderBy('id')->get();
    expect($items)->toHaveCount(2)
        ->and($items[0]->product_name)->toBe('Kakao fermentasi')
        ->and($items[0]->sales_unit)->toBe('karung')
        ->and($items[0]->revenue)->toBe(1200000)
        ->and($items[1]->product_id)->toBeNull()
        ->and($items[1]->production_qty)->toBeNull()
        ->and($report->issues()->where('kind', IssueKind::Problem)->count())->toBe(1)
        ->and($report->issues()->where('kind', IssueKind::Support)->sole()->description)->toBeNull();

    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload())->assertOk();
    expect($report->items()->count())->toBe(0)->and($report->issues()->count())->toBe(0);
});

test('simpan draf menolak produk milik kelompok lain', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);
    $foreign = Product::factory()->for(Organization::factory())->create();

    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload([
        'items' => [['product_id' => $foreign->id, 'product_name' => 'Produk lain']],
    ]))->assertJsonValidationErrors(['items.0.product_id' => 'Produk tidak ditemukan']);
});

test('simpan draf ditolak 409 bila laporan sudah disimpan lebih dulu dari perangkat lain', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);
    $seen = $report->updated_at->getTimestamp();

    Carbon::setTestNow(now()->addMinute());
    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload(['version' => $seen]))->assertOk();

    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload(['version' => $seen, 'activity_summary' => 'Versi lama']))
        ->assertStatus(409);

    expect($report->fresh()->activity_summary)->toBe('Produksi kopi bubuk');
});

test('laporan yang diajukan atau terverifikasi terkunci untuk form dan simpan draf', function (ReportStatus $status) {
    $report = MonthlyReport::factory()->for($this->organization)->status($status)->create(['period' => '2026-09-01', 'activity_summary' => 'Asli']);

    $this->get("/kelompok/laporan/{$report->id}/ubah")->assertForbidden();
    $this->patchJson("/kelompok/laporan/{$report->id}/draf", draftPayload())->assertForbidden();

    expect($report->fresh()->activity_summary)->toBe('Asli');
})->with([ReportStatus::Submitted, ReportStatus::Verified]);

test('salin dari bulan lalu hanya memakai laporan sebelumnya milik kelompok sendiri', function () {
    $older = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-08-01', 'members_active' => 12, 'workforce_count' => 4]);
    $older->items()->create(['product_name' => 'Pinang kering', 'production_unit' => 'kg', 'sales_unit' => 'karung', 'production_qty' => 10]);
    $foreign = MonthlyReport::factory()->for(Organization::factory())->create(['period' => '2026-08-01', 'members_active' => 99]);
    $foreign->items()->create(['product_name' => 'Produk asing']);
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);

    $this->get("/kelompok/laporan/{$report->id}/ubah")
        ->assertOk()
        ->assertSee('Salin dari bulan lalu')
        ->assertSee('Pinang kering')
        ->assertDontSee('Produk asing');

    $previous = ReportForm::previous($report);
    expect($previous['members_active'])->toBe('12')
        ->and($previous['items'][0])->toBe(['product_id' => null, 'product_name' => 'Pinang kering', 'production_unit' => 'kg', 'sales_unit' => 'karung']);
});
