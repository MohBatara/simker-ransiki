<?php

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\ReportReview;
use App\Models\User;
use App\Support\Exports\Pdf;
use App\Support\Exports\ReportRecap;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Spatie\SimpleExcel\SimpleExcelReader;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jayapura'));

    $this->opd = Opd::factory()->create(['name' => 'Dinas Contoh', 'short_name' => 'Contoh']);
    $this->otherOpd = Opd::factory()->create(['name' => 'Dinas Lain', 'short_name' => 'Lain']);
    $this->ours = Organization::factory()->create(['name' => 'KUB Binaan Satu', 'members_count' => null]);
    $this->ours->opds()->attach($this->opd);
    $this->second = Organization::factory()->create(['name' => 'KUB Binaan Dua', 'members_count' => 0]);
    $this->second->opds()->attach($this->opd);
    $this->foreign = Organization::factory()->create(['name' => 'KUB Bukan Binaan', 'members_count' => 12]);
    $this->foreign->opds()->attach($this->otherOpd);

    $this->admin = User::factory()->admin()->create(['name' => 'Admin Uji']);
    $this->reviewer = User::factory()->opd($this->opd)->create(['name' => 'Petugas Uji']);
});

afterEach(fn () => Carbon::setTestNow());

/** Isi satu lembar file Excel dari respons unduhan, baris demi baris (tanpa baris judul). */
function sheetRows(TestResponse $response, string $sheet): array
{
    $path = $response->baseResponse->getFile()->getPathname();

    return SimpleExcelReader::create($path, 'xlsx')->noHeaderRow()->fromSheetName($sheet)->getRows()->all();
}

function verifiedReport(Organization $organization, array $attributes = [], array $items = []): MonthlyReport
{
    $report = MonthlyReport::factory()->for($organization)->status(ReportStatus::Verified)->create([
        'period' => '2026-09-01', 'submitted_at' => '2026-10-05 09:00:00', 'verified_at' => '2026-10-06 09:00:00', ...$attributes,
    ]);
    foreach ($items as $item) {
        $report->items()->create($item);
    }

    return $report;
}

// ── Daftar kelompok ──────────────────────────────────────────────────────────

test('OPD hanya mengekspor kelompok binaan, juga bila filter OPD dimanipulasi', function () {
    $this->actingAs($this->reviewer);

    foreach (['/opd/kelompok/unduh', "/opd/kelompok/unduh?opd={$this->otherOpd->id}", '/opd/kelompok/unduh?opd=none', '/opd/kelompok/unduh?q=Bukan'] as $url) {
        $response = $this->get($url)->assertOk();
        $names = collect(sheetRows($response, 'Kelompok'))->skip(1)->pluck(0);

        expect($names)->not->toContain('KUB Bukan Binaan');
    }

    $names = collect(sheetRows($this->get('/opd/kelompok/unduh'), 'Kelompok'))->skip(1)->pluck(0)->all();
    expect($names)->toBe(['KUB Binaan Dua', 'KUB Binaan Satu']);
});

test('ekspor kelompok mengikuti filter, Admin mendapat semua kelompok', function () {
    $this->foreign->update(['status' => 'inactive']);
    $this->actingAs($this->admin);

    $all = collect(sheetRows($this->get('/admin/kelompok/unduh'), 'Kelompok'));
    expect($all->first())->toContain('Nama kelompok', 'OPD terkait', 'Status hukum')
        ->and($all->skip(1)->pluck(0)->all())->toHaveCount(3);

    $response = $this->get('/admin/kelompok/unduh?status=inactive')->assertOk()
        ->assertDownload('kelompok-2026-10-20.xlsx');
    expect(collect(sheetRows($response, 'Kelompok'))->skip(1)->pluck(0)->all())->toBe(['KUB Bukan Binaan']);
});

test('ekspor kelompok menulis NULL sebagai sel kosong dan 0 tetap 0', function () {
    $rows = collect(sheetRows($this->actingAs($this->admin)->get('/admin/kelompok/unduh'), 'Kelompok'));
    $members = array_search('Jumlah anggota', $rows->first());
    $byName = $rows->skip(1)->keyBy(0);

    expect($byName['KUB Binaan Satu'][$members])->toBe('')
        ->and($byName['KUB Binaan Dua'][$members])->toBe(0)
        ->and($byName['KUB Bukan Binaan'][$members])->toBe(12);
});

test('teks yang diawali = + - @ diberi apostrof agar tidak menjadi rumus', function () {
    $this->ours->update(['name' => '=HYPERLINK("http://contoh.test","klik")', 'leader_name' => '@SUM(A1)', 'phone' => '+62 812 0000', 'address' => '-1+1']);

    $response = $this->actingAs($this->admin)->get('/admin/kelompok/unduh');
    $row = collect(sheetRows($response, 'Kelompok'))->first(fn ($row) => str_contains((string) $row[0], 'HYPERLINK'));

    expect($row[0])->toBe('\'=HYPERLINK("http://contoh.test","klik")')
        ->and($row[6])->toBe("'@SUM(A1)")
        ->and($row[7])->toBe("'+62 812 0000")
        ->and($row[9])->toBe("'-1+1");

    // File xlsx tidak berisi sel rumus sama sekali.
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    expect($zip->getFromName('xl/worksheets/sheet1.xml'))->not->toContain('<f>');
});

test('kelompok tidak bisa mengekspor daftar kelompok', function () {
    $this->actingAs(User::factory()->kelompok($this->ours)->create());

    $this->get('/admin/kelompok/unduh')->assertForbidden();
    $this->get('/opd/kelompok/unduh')->assertForbidden();
});

test('tombol Unduh Excel membawa filter aktif', function () {
    $this->actingAs($this->reviewer)->get('/opd/kelompok?status=active&q=Binaan')
        ->assertOk()
        ->assertSee(route('opd.organizations.export', ['q' => 'Binaan', 'status' => 'active']))
        ->assertDontSee('Impor');
});

// ── Rekap laporan ────────────────────────────────────────────────────────────

test('rekap hanya menjumlahkan laporan terverifikasi, NULL tidak dihitung 0', function () {
    verifiedReport($this->ours, ['income_sales' => 100_000, 'members_active' => 5]);
    verifiedReport($this->second, ['income_sales' => null, 'members_active' => 0]);
    MonthlyReport::factory()->for($this->foreign)->status(ReportStatus::Submitted)->create([
        'period' => '2026-09-01', 'submitted_at' => '2026-10-05 09:00:00', 'income_sales' => 999_999,
    ]);

    $response = $this->actingAs($this->admin)->get('/admin/laporan/rekap?periode=2026-09&format=xlsx')
        ->assertOk()->assertDownload('rekap-laporan-2026-09.xlsx');
    $summary = collect(sheetRows($response, 'Ringkasan'))->keyBy(0);

    expect($summary['Laporan terverifikasi'][1])->toBe(2)
        ->and($summary['Pendapatan penjualan (Rp)'][1])->toBe(100000)
        ->and($summary['Pendapatan penjualan (Rp)'][2])->toBe('diisi 1 dari 2 kelompok')
        ->and($summary['Anggota aktif'][1])->toBe(5)
        ->and($summary['Biaya (Rp)'][1])->toBe('')
        ->and($summary['Biaya (Rp)'][2])->toBe('diisi 0 dari 2 kelompok')
        ->and($summary['Menunggu verifikasi'][1])->toBe(1)
        ->and($summary['Terverifikasi'][1])->toBe(2);
});

test('produksi di rekap dikelompokkan per produk dan satuan, tidak dijumlah lintas satuan', function () {
    verifiedReport($this->ours, [], [
        ['product_name' => 'Kakao', 'production_qty' => 10, 'production_unit' => 'kg'],
        ['product_name' => 'Kakao', 'production_qty' => 2, 'production_unit' => 'karung'],
    ]);
    verifiedReport($this->second, [], [
        ['product_name' => ' kakao ', 'production_qty' => 5.5, 'production_unit' => 'KG '],
        ['product_name' => 'Kakao', 'production_qty' => null, 'production_unit' => 'kg', 'sales_qty' => 3, 'sales_unit' => 'kg', 'revenue' => 90_000],
    ]);
    $draft = MonthlyReport::factory()->for($this->foreign)->create(['period' => '2026-09-01']);
    $draft->items()->create(['product_name' => 'Kakao', 'production_qty' => 1000, 'production_unit' => 'kg']);

    $response = $this->actingAs($this->admin)->get('/admin/laporan/rekap?periode=2026-09');
    $production = collect(sheetRows($response, 'Produksi'))->skip(1)->values();

    expect($production->all())->toBe([
        ['Kakao', 'karung', 2, 'diisi 1 dari 1 kelompok'],
        ['Kakao', 'kg', 15.5, 'diisi 2 dari 2 kelompok'],
    ]);
    expect(collect(sheetRows($response, 'Penjualan'))->skip(1)->values()->all())->toBe([
        ['Kakao', 'kg', 3, 90000, 'diisi 1 dari 1 kelompok'],
    ]);
});

test('status pelaporan di rekap memakai aturan terlambat dan hanya kelompok binaan untuk OPD', function () {
    // Diajukan lalu diminta revisi: tidak terlambat. Draf yang belum diajukan setelah tenggat: terlambat.
    MonthlyReport::factory()->for($this->ours)->status(ReportStatus::Revision)->create(['period' => '2026-09-01', 'submitted_at' => '2026-10-04 09:00:00']);
    MonthlyReport::factory()->for($this->second)->create(['period' => '2026-09-01']);
    Organization::factory()->closed()->create()->opds()->attach($this->opd);

    $response = $this->actingAs($this->reviewer)->get("/opd/laporan/rekap?periode=2026-09&opd={$this->otherOpd->id}")->assertOk();
    $states = collect(sheetRows($response, 'Status laporan'))->skip(1)->pluck(4, 0)->all();

    expect($states)->toBe(['KUB Binaan Dua' => 'Terlambat', 'KUB Binaan Satu' => 'Perlu revisi']);
});

test('periode rekap yang tidak sah kembali ke bulan wajib lapor', function () {
    $this->actingAs($this->admin);

    $this->get('/admin/laporan/rekap?periode=2027-01')->assertDownload('rekap-laporan-2026-09.xlsx');
    $this->get('/admin/laporan/rekap?periode=abc')->assertDownload('rekap-laporan-2026-09.xlsx');
    $this->get('/admin/laporan/rekap?periode=2026-08')->assertDownload('rekap-laporan-2026-08.xlsx');
});

test('rekap PDF bisa diunduh Admin dan OPD, tidak oleh kelompok', function () {
    verifiedReport($this->ours, ['income_sales' => 100_000], [['product_name' => 'Kakao', 'production_qty' => 10, 'production_unit' => 'kg']]);

    $response = $this->actingAs($this->reviewer)->get('/opd/laporan/rekap?periode=2026-09&format=pdf')->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('rekap-laporan-2026-09.pdf')
        ->and($response->getContent())->toStartWith('%PDF');

    $this->actingAs(User::factory()->kelompok($this->ours)->create());
    $this->get('/opd/laporan/rekap?format=pdf')->assertForbidden();
    $this->get('/admin/laporan/rekap')->assertForbidden();
});

test('PDF rekap memuat kepala, kaki, dan hanya kelompok binaan', function () {
    $recap = new ReportRecap($this->reviewer, Carbon::parse('2026-09-01')->toImmutable());
    $html = view('shared.exports.recap', ['recap' => $recap, 'scope' => 'Kelompok binaan Dinas Contoh', 'printedLine' => Pdf::printedLine($this->reviewer)])->render();

    expect($html)->toContain('Rekap laporan bulanan', 'Periode: September 2026', 'Dicetak pada 20 Oktober 2026 12.00 WIT oleh Petugas Uji')
        ->toContain('Angka resmi hanya dari laporan terverifikasi.')
        ->toContain('KUB Binaan Satu')
        ->not->toContain('KUB Bukan Binaan');
});

test('modal rekap tampil di Semua Laporan dengan pilihan bulan', function () {
    $this->actingAs($this->reviewer)->get('/opd/laporan')
        ->assertOk()
        ->assertSee('Unduh rekap')
        ->assertSee(route('opd.reports.recap'), false)
        ->assertSee('September 2026');
});

// ── PDF satu laporan ─────────────────────────────────────────────────────────

test('PDF laporan bisa diunduh pemilik, OPD terkait, dan Admin; yang lain 404', function () {
    $report = verifiedReport($this->ours, ['income_sales' => 100_000]);
    $owner = User::factory()->kelompok($this->ours)->create();
    $otherKelompok = User::factory()->kelompok($this->foreign)->create();
    $foreignOpd = User::factory()->opd($this->otherOpd)->create();

    $this->actingAs($owner)->get("/kelompok/laporan/{$report->id}/pdf")->assertOk()
        ->assertDownload("laporan-{$this->ours->slug}-2026-09.pdf");
    $this->actingAs($this->reviewer)->get("/opd/laporan/{$report->id}/pdf")->assertOk();
    $this->actingAs($this->admin)->get("/admin/laporan/{$report->id}/pdf")->assertOk();

    $this->actingAs($otherKelompok)->get("/kelompok/laporan/{$report->id}/pdf")->assertNotFound();
    $this->actingAs($foreignOpd)->get("/opd/laporan/{$report->id}/pdf")->assertNotFound();
    $this->actingAs($owner)->get("/opd/laporan/{$report->id}/pdf")->assertForbidden();
});

test('draf yang belum pernah diajukan tidak bisa diunduh peninjau', function () {
    $draft = MonthlyReport::factory()->for($this->ours)->create(['period' => '2026-09-01']);

    $this->actingAs($this->admin)->get("/admin/laporan/{$draft->id}/pdf")->assertNotFound();
    $this->actingAs(User::factory()->kelompok($this->ours)->create())->get("/kelompok/laporan/{$draft->id}/pdf")->assertOk();
});

test('PDF laporan memuat bagian A–H, catatan verifikasi, dan NULL ditulis kosong', function () {
    $report = verifiedReport($this->ours, ['activity_summary' => 'Panen kakao', 'income_sales' => 250_000, 'income_other' => null, 'costs' => 0], [
        ['product_name' => 'Kakao', 'production_qty' => 10, 'production_unit' => 'kg', 'revenue' => null],
    ]);
    ReportReview::forceCreate(['monthly_report_id' => $report->id, 'user_id' => $this->reviewer->id, 'action' => ReviewAction::Verified, 'note' => 'Data sudah sesuai nota.', 'created_at' => now()]);

    $html = view('shared.exports.report', [
        'report' => $report->loadDetail(), 'attachments' => collect(), 'printedLine' => Pdf::printedLine($this->admin),
    ])->render();

    expect($html)->toContain('Laporan bulanan', 'KUB Binaan Satu', 'Periode: September 2026', 'oleh Admin Uji')
        ->toContain('A. Aktivitas', 'B. Produksi', 'C. Sumber daya manusia', 'D. Keuangan', 'E. Permasalahan', 'F. Kebutuhan dukungan', 'G. Dokumentasi', 'H. Riwayat')
        ->toContain('Panen kakao', 'Rp 250.000', 'Rp 0', 'Data sudah sesuai nota.', 'Laba tidak dihitung')
        ->toContain('<tr><td>Pendapatan lain</td><td></td></tr>');
});

test('tombol Unduh PDF tampil di detail laporan untuk semua peran', function () {
    $report = verifiedReport($this->ours);

    $this->actingAs(User::factory()->kelompok($this->ours)->create())->get("/kelompok/laporan/{$report->id}")
        ->assertOk()->assertSee(route('kelompok.reports.pdf', $report), false);
    $this->actingAs($this->reviewer)->get("/opd/laporan/{$report->id}")
        ->assertOk()->assertSee(route('opd.reports.pdf', $report), false);
});
