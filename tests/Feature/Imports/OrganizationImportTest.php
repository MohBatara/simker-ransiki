<?php

use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Models\Village;
use App\Notifications\PossibleDuplicate;
use App\Support\Imports\OrganizationImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

beforeEach(function () {
    Storage::fake('local');

    $this->admin = User::factory()->admin()->create();
    $this->type = InstitutionType::factory()->create(['name' => 'Kelompok Pertanian', 'code' => 'KT']);
    $this->village = Village::factory()->create(['name' => 'Kampung Contoh 1']);
    $this->otherVillage = Village::factory()->create(['name' => 'Kampung Contoh 2']);
    $this->sector = BusinessSector::factory()->create(['name' => 'Perkebunan']);
    $this->sector2 = BusinessSector::factory()->create(['name' => 'Pengolahan Hasil']);
    $this->opd = Opd::factory()->create(['name' => 'Dinas Pertanian dan Perkebunan', 'short_name' => 'Distanbun']);
});

/** Baris data dengan urutan kolom template; kunci yang tidak diisi menjadi sel kosong. */
function importRow(array $values): array
{
    return array_map(fn (string $key) => $values[$key] ?? null, array_keys(OrganizationImport::COLUMNS));
}

function importUpload(array $rows, string $type = 'xlsx', ?array $header = null, string $delimiter = ','): UploadedFile
{
    $path = sys_get_temp_dir().'/impor-'.uniqid().'.'.$type;
    $writer = SimpleExcelWriter::create($path, delimiter: $delimiter)->noHeaderRow();
    $writer->addRow($header ?? OrganizationImport::headers());
    foreach ($rows as $row) {
        $writer->addRow($row);
    }
    $writer->close();

    return new UploadedFile($path, "data.{$type}", null, null, true);
}

function uploadImport(object $test, array $rows, ...$options): TestResponse
{
    return $test->actingAs($test->admin)->post('/admin/kelompok/impor', ['file' => importUpload($rows, ...$options)]);
}

/** Token dari URL pratinjau setelah unggah. */
function importToken(TestResponse $response): string
{
    $response->assertRedirect();

    return basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
}

function validRow(array $overrides = []): array
{
    return importRow([
        'name' => 'Kelompok Tani Kakao Baru', 'type' => 'Kelompok Pertanian', 'village' => 'Kampung Contoh 1',
        'sectors' => 'Perkebunan', ...$overrides,
    ]);
}

test('hanya Admin yang bisa membuka dan menjalankan impor', function (string $role) {
    $organization = Organization::factory()->create();
    $user = $role === 'opd' ? User::factory()->opd($this->opd)->create() : User::factory()->kelompok($organization)->create();
    $this->actingAs($user);

    $this->get('/admin/kelompok/impor')->assertForbidden();
    $this->get('/admin/kelompok/impor/template')->assertForbidden();
    $this->post('/admin/kelompok/impor', ['file' => importUpload([validRow()])])->assertForbidden();
    $this->get('/opd/kelompok/impor')->assertNotFound();
    expect(Organization::count())->toBe(1);
})->with(['opd', 'kelompok']);

test('tombol Impor tampil di daftar kelompok Admin', function () {
    $this->actingAs($this->admin)->get('/admin/kelompok')->assertOk()
        ->assertSee(route('admin.organizations.import.create'))
        ->assertSee('Unduh Excel');
    $this->get('/admin/kelompok/impor')->assertOk()->assertSee('Unduh template')->assertSee('Periksa file');
});

test('template berisi sheet Data dan Petunjuk dengan nilai dari data master', function () {
    $response = $this->actingAs($this->admin)->get('/admin/kelompok/impor/template')->assertOk()
        ->assertDownload('template-impor-kelompok.xlsx');
    $path = $response->baseResponse->getFile()->getPathname();

    $data = SimpleExcelReader::create($path, 'xlsx')->noHeaderRow()->fromSheetName('Data')->getRows()->all();
    $guide = collect(SimpleExcelReader::create($path, 'xlsx')->noHeaderRow()->fromSheetName('Petunjuk')->getRows()->all())->pluck(0)->filter()->all();

    expect($data)->toHaveCount(1)
        ->and($data[0])->toContain('Nama kelompok (wajib)', 'Kampung (wajib)', 'OPD terkait', 'Jumlah anggota')
        ->and($guide)->toContain('Kelompok Pertanian', 'Kampung Contoh 1', 'Kampung Contoh 2', 'Perkebunan', 'Dinas Pertanian dan Perkebunan (Distanbun)');
});

test('pratinjau tidak menyimpan apa pun dan memberi status per baris', function () {
    Organization::factory()->create(['name' => 'KUB Sagu Lama', 'village_id' => $this->village->id]);

    $token = importToken(uploadImport($this, [
        validRow(),
        validRow(['name' => 'KUB Sagu Lama']),
        validRow(['name' => 'Kelompok Tanpa Jenis', 'type' => null]),
    ]));

    $this->get("/admin/kelompok/impor/{$token}")->assertOk()
        ->assertSeeInOrder(['Kelompok Tani Kakao Baru', 'Siap', 'KUB Sagu Lama', 'Peringatan', 'Sudah ada di database', 'Kelompok Tanpa Jenis', 'Gagal', 'Jenis kelembagaan wajib diisi.'])
        ->assertSee('Impor <span x-text="ready + picked.length">1</span> baris', false);

    expect(Organization::count())->toBe(1)
        ->and(DB::table('organization_sector')->count())->toBe(0);
});

test('data master dicocokkan tanpa peduli huruf besar-kecil dan spasi, nilai yang tidak ada gagal', function () {
    $token = importToken(uploadImport($this, [
        validRow(['name' => 'Satu', 'type' => '  kelompok   PERTANIAN ', 'village' => 'kampung contoh 1', 'sectors' => 'perkebunan ;  PENGOLAHAN hasil', 'opds' => 'distanbun']),
        validRow(['name' => 'Dua', 'type' => 'kt']),
        validRow(['name' => 'Tiga', 'village' => 'Kampung Tidak Ada', 'sectors' => 'Perkebunan; Pariwisata', 'opds' => 'Dinas Pariwisata']),
    ]));

    $rows = collect(OrganizationImport::read(Storage::disk('local')->path("imports/{$token}.xlsx"), 'xlsx')->rows)->keyBy(fn ($row) => $row['values']['name']);

    expect($rows['Satu']['status'])->toBe('ready')
        ->and($rows['Satu']['data']['sectors'])->toEqualCanonicalizing([$this->sector->id, $this->sector2->id])
        ->and($rows['Satu']['data']['opds'])->toBe([$this->opd->id])
        ->and($rows['Dua']['status'])->toBe('ready')
        ->and($rows['Tiga']['status'])->toBe('failed')
        ->and($rows['Tiga']['messages'])->toBe([
            'Kampung "Kampung Tidak Ada" tidak ada di data master.',
            'Bidang usaha "Pariwisata" tidak ada di data master.',
            'OPD terkait "Dinas Pariwisata" tidak ada di data master.',
        ]);
});

test('duplikat di file yang sama dan nama mirip di database diberi peringatan', function () {
    Organization::factory()->create(['name' => 'Kelompok Tani Pinang Jaya', 'village_id' => $this->village->id]);

    $token = importToken(uploadImport($this, [
        validRow(['name' => 'KUB Ikan Asap']),
        validRow(['name' => 'kub  ikan asap']),
        validRow(['name' => 'KUB Ikan Asap', 'village' => 'Kampung Contoh 2']),
        validRow(['name' => 'Kel. Tani Pinang Jaya']),
    ]));

    $rows = OrganizationImport::read(Storage::disk('local')->path("imports/{$token}.xlsx"), 'xlsx')->rows;

    expect(array_column($rows, 'status'))->toBe(['ready', 'warning', 'ready', 'warning'])
        ->and($rows[1]['messages'])->toBe(['Nama dan kampung sama dengan baris 2 di file ini.'])
        ->and($rows[3]['messages'][0])->toContain('Kelompok Tani Pinang Jaya');
});

test('logika data ganda sama dengan form: mirip di kampung sama = peringatan, di kampung lain = catatan saja', function () {
    Organization::factory()->create(['name' => 'KUB Sagu Lestari', 'village_id' => $this->otherVillage->id]);

    $token = importToken(uploadImport($this, [
        validRow(['name' => 'Koperasi Sagu Lestari']),
        validRow(['name' => 'Kelompok Kakao Harapan Jaya']),
        validRow(['name' => 'Kel. Kakao Harapan Jaja']),
    ]));

    $rows = OrganizationImport::read(Storage::disk('local')->path("imports/{$token}.xlsx"), 'xlsx')->rows;

    expect(array_column($rows, 'status'))->toBe(['ready', 'ready', 'warning'])
        ->and($rows[0]['messages'])->toBe(['Nama mirip dengan KUB Sagu Lestari di Kampung Contoh 2 (kampung lain).'])
        ->and($rows[2]['messages'])->toBe(['Mirip dengan baris 3 di file ini, di kampung yang sama.']);
});

test('impor menyimpan baris siap dan peringatan yang dipilih, dengan bawaan yang benar', function () {
    Organization::factory()->create(['name' => 'KUB Sagu Lama', 'village_id' => $this->village->id]);

    $token = importToken(uploadImport($this, [
        validRow(['sectors' => 'Perkebunan; Pengolahan Hasil', 'opds' => 'Distanbun', 'members_count' => null, 'workforce_count' => 0, 'founded_year' => 2019, 'phone' => 81234567890, 'reporting_starts_on' => '2026-09']),
        validRow(['name' => 'KUB Sagu Lama']),
        validRow(['name' => 'KUB Sagu Lama', 'village' => 'Kampung Contoh 2', 'members_count' => '1.250']),
        validRow(['name' => 'Kelompok Gagal', 'sectors' => null]),
    ]));

    // Baris 3 (peringatan) tidak dicentang: dilewati.
    $this->post("/admin/kelompok/impor/{$token}", ['include' => []])
        ->assertRedirect("/admin/kelompok/impor/{$token}/hasil");

    $created = Organization::where('created_by', $this->admin->id)->orderBy('id')->get();
    expect($created)->toHaveCount(2);

    $first = $created[0]->load(['sectors', 'opds']);
    expect($first->name)->toBe('Kelompok Tani Kakao Baru')
        ->and($first->status->value)->toBe('active')
        ->and($first->is_public)->toBeFalse()
        ->and($first->members_count)->toBeNull()
        ->and($first->workforce_count)->toBe(0)
        ->and($first->founded_year)->toBe(2019)
        ->and($first->phone)->toBe('081234567890')
        ->and($first->reporting_starts_on->format('Y-m-d'))->toBe('2026-09-01')
        ->and($first->sectors->pluck('id')->all())->toEqualCanonicalizing([$this->sector->id, $this->sector2->id])
        ->and($first->opds->pluck('id')->all())->toBe([$this->opd->id])
        // Dibuat petugas lewat impor: langsung terverifikasi, tercatat di Riwayat.
        ->and($first->profile_status)->toBe(App\Enums\ProfileStatus::Verified)
        ->and($first->profile_verified_by)->toBe($this->admin->id)
        ->and(App\Models\ChangeLog::where('organization_id', $first->id)->where('field', 'profile_status')->value('new_value'))
        ->toBe('Diverifikasi otomatis saat dibuat oleh '.$this->admin->name)
        ->and($created[1]->village_id)->toBe($this->otherVillage->id)
        ->and($created[1]->members_count)->toBe(1250)
        ->and(User::count())->toBe(1);

    $this->get("/admin/kelompok/impor/{$token}/hasil")->assertOk()
        ->assertSeeInOrder(['Berhasil', '2', 'Dilewati', '1', 'Gagal', '1'])
        ->assertSee('Unduh baris gagal');
});

test('baris peringatan yang dicentang ikut diimpor', function () {
    Organization::factory()->create(['name' => 'KUB Sagu Lama', 'village_id' => $this->village->id]);
    $token = importToken(uploadImport($this, [validRow(['name' => 'KUB Sagu Lama'])]));

    $this->post("/admin/kelompok/impor/{$token}", ['include' => ['2']])->assertRedirect();

    expect(Organization::where('name', 'KUB Sagu Lama')->count())->toBe(2);
});

test('gagal di tengah impor tidak meninggalkan data setengah tersimpan', function () {
    $token = importToken(uploadImport($this, [
        validRow(['name' => 'Baris Pertama']),
        validRow(['name' => 'Baris Kedua']),
        validRow(['name' => 'Baris Ketiga']),
    ]));

    Organization::creating(function (Organization $organization) {
        if ($organization->name === 'Baris Ketiga') {
            throw new RuntimeException('Simulasi kesalahan database');
        }
    });

    $this->from("/admin/kelompok/impor/{$token}")->post("/admin/kelompok/impor/{$token}")
        ->assertRedirect("/admin/kelompok/impor/{$token}")
        ->assertSessionHas('error', 'Impor dibatalkan karena terjadi kesalahan saat menyimpan. Tidak ada data yang disimpan. Coba lagi.');

    expect(Organization::count())->toBe(0)
        ->and(DB::table('organization_sector')->count())->toBe(0)
        ->and(Storage::disk('local')->exists("imports/{$token}.xlsx"))->toBeTrue();
});

test('token yang sudah diimpor tidak bisa diimpor dua kali', function () {
    $token = importToken(uploadImport($this, [validRow()]));

    $this->post("/admin/kelompok/impor/{$token}")->assertRedirect("/admin/kelompok/impor/{$token}/hasil");
    $this->post("/admin/kelompok/impor/{$token}")->assertRedirect("/admin/kelompok/impor/{$token}/hasil");
    $this->get("/admin/kelompok/impor/{$token}")->assertRedirect("/admin/kelompok/impor/{$token}/hasil");

    expect(Organization::count())->toBe(1);
});

test('unggahan Admin lain tidak bisa dibuka', function () {
    $token = importToken(uploadImport($this, [validRow()]));

    $this->actingAs(User::factory()->admin()->create());
    $this->get("/admin/kelompok/impor/{$token}")->assertNotFound();
    $this->post("/admin/kelompok/impor/{$token}")->assertNotFound();
    $this->get("/admin/kelompok/impor/{$token}/hasil")->assertNotFound();
    expect(Organization::count())->toBe(0);
});

test('daftar baris gagal bisa diunduh beserta alasannya', function () {
    $token = importToken(uploadImport($this, [validRow(), validRow(['name' => '@SUM(A1)', 'village' => 'Kampung X'])]));
    $this->post("/admin/kelompok/impor/{$token}");

    $response = $this->get("/admin/kelompok/impor/{$token}/gagal")->assertOk();
    $rows = SimpleExcelReader::create($response->baseResponse->getFile()->getPathname(), 'xlsx')->noHeaderRow()->getRows()->all();

    expect($rows[0])->toContain('Baris', 'Alasan', 'Nama kelompok (wajib)')
        ->and($rows[1][0])->toBe(3)
        ->and($rows[1][1])->toBe('Kampung "Kampung X" tidak ada di data master.')
        ->and($rows[1][2])->toBe("'@SUM(A1)");
});

test('CSV dengan pemisah titik koma bisa dibaca', function () {
    $token = importToken(uploadImport($this, [validRow(['sectors' => 'Perkebunan'])], 'csv', null, ';'));

    $this->get("/admin/kelompok/impor/{$token}")->assertOk()->assertSee('Kelompok Tani Kakao Baru')->assertSee('Siap');
});

test('file yang tidak sesuai ditolak dengan pesan yang jelas', function () {
    $this->actingAs($this->admin);

    $this->post('/admin/kelompok/impor', ['file' => realUpload('data.xlsx', '%PDF-1.4 bukan spreadsheet')])
        ->assertSessionHasErrors(['file' => 'Gunakan file .xlsx atau .csv. Di Excel: File > Simpan sebagai > Buku Kerja Excel (.xlsx).']);
    $this->post('/admin/kelompok/impor', ['file' => UploadedFile::fake()->create('besar.csv', 2100, 'text/csv')])
        ->assertSessionHasErrors(['file' => 'Ukuran file paling besar 2 MB. Bagi menjadi beberapa file.']);

    uploadImport($this, [validRow()], 'xlsx', ['Nama', 'Jenis'])
        ->assertSessionHasErrors(['file' => 'Kolom wajib tidak ditemukan: Nama kelompok, Jenis kelembagaan, Kampung, Bidang usaha. Pakai judul kolom dari template.']);
    uploadImport($this, [])->assertSessionHasErrors(['file' => 'Belum ada baris data. Isi data mulai baris kedua, di bawah judul kolom.']);
    uploadImport($this, array_fill(0, 501, validRow()))
        ->assertSessionHasErrors(['file' => 'File berisi lebih dari 500 baris data. Bagi menjadi beberapa file.']);

    expect(Storage::disk('local')->files('imports'))->toBe([]);
});

test('angka, email, dan bulan wajib lapor yang tidak valid membuat baris gagal', function () {
    $token = importToken(uploadImport($this, [
        validRow(['members_count' => 'banyak', 'email' => 'bukan-email', 'reporting_starts_on' => '2026-13', 'founded_year' => 1800]),
    ]));

    $row = OrganizationImport::read(Storage::disk('local')->path("imports/{$token}.xlsx"), 'xlsx')->rows[0];

    expect($row['status'])->toBe('failed')
        ->and(implode(' ', $row['messages']))->toContain('Mulai wajib lapor', 'Email', 'Jumlah anggota', 'Tahun berdiri');
});

test('impor yang memuat kemungkinan data ganda mengirim satu notifikasi ringkas ke admin', function () {
    Organization::factory()->create(['name' => 'KUB Sagu Lama', 'village_id' => $this->village->id]);

    $token = importToken(uploadImport($this, [
        validRow(['name' => 'KUB Sagu Lama', 'village' => 'Kampung Contoh 2']),
        validRow(['name' => 'Koperasi Sagu Lama', 'village' => 'Kampung Contoh 2']),
        validRow(['name' => 'Kelompok Kakao Baru Sekali']),
    ]));
    $this->post("/admin/kelompok/impor/{$token}", ['include' => [3]]);

    $notification = $this->admin->notifications()->where('type', PossibleDuplicate::class)->sole();
    expect($notification->data)->toMatchArray(['source' => 'impor', 'count' => 2]);
});
