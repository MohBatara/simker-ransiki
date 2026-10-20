<?php

use App\Enums\ReportStatus;
use App\Models\Attachment;
use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Rules\AllowedUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->opd = Opd::factory()->create();
    $this->organization = Organization::factory()->public()->create();
    $this->organization->opds()->attach($this->opd);
    $this->owner = User::factory()->kelompok($this->organization)->create();
    $this->other = Organization::factory()->create();
});

function uploadTo(object $test, string $url, UploadedFile $file)
{
    return $test->postJson($url, ['file' => $file]);
}

test('foto HP diputar sesuai EXIF, diperkecil, dan semua metadata termasuk GPS dibuang', function () {
    $original = jpegWithExif(40, 20, orientation: 6);
    expect(exifOf($original))->toHaveKeys(['Orientation', 'GPSLatitudeRef']);

    $this->actingAs($this->owner);
    uploadTo($this, "/lampiran/kelompok/{$this->organization->id}", realUpload('foto-hp.jpg', $original))->assertCreated();

    $attachment = Attachment::firstOrFail();
    $stored = Storage::disk('local')->get($attachment->path);
    [$width, $height] = getimagesizefromstring($stored);

    expect($attachment->path)->toMatch('#^attachments/organization/\d+/[0-9a-f-]{36}\.jpg$#')
        ->and($attachment->original_name)->toBe('foto-hp.jpg')
        ->and([$width, $height])->toBe([20, 40])
        ->and(exifOf($stored))->not->toHaveKey('GPSLatitudeRef')
        ->and(exifOf($stored))->not->toHaveKey('Orientation')
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('gambar besar diperkecil sampai sisi terpanjang 1600 px', function () {
    $this->actingAs($this->owner);
    uploadTo($this, "/lampiran/kelompok/{$this->organization->id}", realUpload('lebar.png', imageBytes(3200, 1000, 'png')))->assertCreated();

    $attachment = Attachment::firstOrFail();
    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($attachment->path));

    expect([$width, $height])->toBe([1600, 500])->and($attachment->mime_type)->toBe('image/png');
});

test('PDF disimpan apa adanya', function () {
    $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    $this->actingAs($this->owner);

    uploadTo($this, "/lampiran/kelompok/{$this->organization->id}", realUpload('surat izin.pdf', $pdf))->assertCreated();

    $attachment = Attachment::firstOrFail();
    expect(Storage::disk('local')->get($attachment->path))->toBe($pdf)
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->original_name)->toBe('surat izin.pdf');
});

test('file ditolak dengan pesan berbahasa Indonesia', function (Closure $makeFile, string $message) {
    $this->actingAs($this->owner);

    uploadTo($this, "/lampiran/kelompok/{$this->organization->id}", $makeFile())
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', $message);

    expect(Attachment::count())->toBe(0);
})->with([
    'PHP berekstensi jpg' => [fn () => realUpload('foto.jpg', '<?php echo "halo"; ?>'), 'Tipe file tidak didukung. Gunakan JPG, PNG, WebP, foto iPhone (HEIC), atau PDF.'],
    'teks berekstensi pdf' => [fn () => realUpload('surat.pdf', 'bukan pdf'), 'Tipe file tidak didukung. Gunakan JPG, PNG, WebP, foto iPhone (HEIC), atau PDF.'],
    'HEIC' => [fn () => realUpload('IMG_0001.HEIC', "\0\0\0\x18ftypheic\0\0\0\0mif1heic"), AllowedUpload::HEIC_MESSAGE],
    'lebih dari 10 MB' => [fn () => realUpload('besar.pdf', '%PDF-1.4'.str_repeat('0', 10 * 1024 * 1024 + 10)), 'Ukuran file paling besar 10 MB. Perkecil file lalu coba lagi.'],
    'lebih dari 8000 piksel' => [fn () => realUpload('panjang.png', imageBytes(8001, 10, 'png')), 'Gambar terlalu besar (lebih dari 8000 piksel per sisi). Perkecil gambar lalu coba lagi.'],
]);

test('maksimal 10 file per entitas', function () {
    Attachment::factory()->count(10)->create(['attachable_id' => $this->organization->id]);
    $this->actingAs($this->owner);

    uploadTo($this, "/lampiran/kelompok/{$this->organization->id}", realUpload('ke-11.jpg', imageBytes(10, 10)))
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'Sudah ada 10 file. Hapus file lama untuk menambah yang baru.');
});

test('tidak bisa mengunggah ke kelompok atau izin lain', function () {
    $license = License::factory()->for($this->other)->create();
    $this->actingAs($this->owner);

    uploadTo($this, "/lampiran/kelompok/{$this->other->id}", realUpload('a.jpg', imageBytes(10, 10)))->assertNotFound();
    uploadTo($this, "/lampiran/izin/{$license->id}", realUpload('a.jpg', imageBytes(10, 10)))->assertNotFound();

    expect(Attachment::count())->toBe(0);
});

test('lampiran izin bisa diunggah pemilik dan OPD pembina', function () {
    $license = License::factory()->for($this->organization)->create();

    $this->actingAs($this->owner);
    uploadTo($this, "/lampiran/izin/{$license->id}", realUpload('izin.jpg', imageBytes(10, 10)))->assertCreated();
    $this->actingAs(User::factory()->opd($this->opd)->create());
    uploadTo($this, "/lampiran/izin/{$license->id}", realUpload('izin-2.jpg', imageBytes(10, 10)))->assertCreated();

    expect($license->attachments()->count())->toBe(2);
});

test('lampiran privat hanya bisa dibuka yang berhak', function (string $viewer, int $status) {
    $attachment = Attachment::factory()->create(['attachable_id' => $this->organization->id, 'mime_type' => 'application/pdf']);
    Storage::disk('local')->put($attachment->path, '%PDF-1.4');

    $user = match ($viewer) {
        'pemilik' => $this->owner,
        'OPD pembina' => User::factory()->opd($this->opd)->create(),
        'admin' => User::factory()->admin()->create(),
        'kelompok lain' => User::factory()->kelompok($this->other)->create(),
        'OPD lain' => User::factory()->opd()->create(),
    };

    $this->actingAs($user)->get("/lampiran/{$attachment->id}")->assertStatus($status);
    $this->get("/lampiran/{$attachment->id}/unduh")->assertStatus($status);
})->with([
    ['pemilik', 200], ['OPD pembina', 200], ['admin', 200], ['kelompok lain', 404], ['OPD lain', 404],
]);

test('tamu tidak bisa membuka lampiran', function () {
    $attachment = Attachment::factory()->create(['attachable_id' => $this->organization->id]);

    $this->get("/lampiran/{$attachment->id}")->assertRedirect('/login');
});

test('PDF dibuka inline dalam sandbox dan bisa diunduh terpisah', function () {
    $attachment = Attachment::factory()->create(['attachable_id' => $this->organization->id, 'mime_type' => 'application/pdf', 'original_name' => 'surat.pdf']);
    Storage::disk('local')->put($attachment->path, '%PDF-1.4');
    $this->actingAs($this->owner);

    $inline = $this->get("/lampiran/{$attachment->id}");
    expect($inline->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($inline->headers->get('Content-Security-Policy'))->toBe('sandbox')
        ->and($inline->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($inline->headers->get('Content-Type'))->toBe('application/pdf');

    expect($this->get("/lampiran/{$attachment->id}/unduh")->headers->get('Content-Disposition'))->toStartWith('attachment');
});

test('menghapus lampiran menghapus file fisiknya', function () {
    $attachment = Attachment::factory()->create(['attachable_id' => $this->organization->id]);
    Storage::disk('local')->put($attachment->path, 'isi');

    $this->actingAs(User::factory()->kelompok($this->other)->create())->delete("/lampiran/{$attachment->id}")->assertNotFound();
    $this->actingAs($this->owner)->delete("/lampiran/{$attachment->id}")->assertSessionHas('status', 'File dihapus.');

    expect($attachment->fresh())->toBeNull()->and(Storage::disk('local')->exists($attachment->path))->toBeFalse();
});

test('lampiran laporan terverifikasi tidak bisa dihapus siapa pun, termasuk admin', function () {
    $report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Verified)->create();
    $attachment = Attachment::factory()->create(['attachable_type' => 'monthly_report', 'attachable_id' => $report->id]);

    $this->actingAs(User::factory()->admin()->create())->delete("/lampiran/{$attachment->id}")->assertForbidden();

    expect($attachment->fresh())->not->toBeNull();
});

test('foto produk disimpan sebagai JPEG beserta versi kecil, dan foto lama diganti', function () {
    $product = Product::factory()->for($this->organization)->create();
    $this->actingAs($this->owner);

    $this->postJson("/foto-produk/{$product->id}", ['file' => realUpload('produk.png', imageBytes(2000, 1000, 'png'))])->assertCreated();
    $first = $product->fresh();
    $photo = Storage::disk('local')->get($first->photo_path);

    expect(getimagesizefromstring($photo)['mime'])->toBe('image/jpeg')
        ->and(getimagesizefromstring($photo)[0])->toBe(1600)
        ->and(getimagesizefromstring(Storage::disk('local')->get($first->thumbnailPath()))[0])->toBe(400);

    $this->postJson("/foto-produk/{$product->id}", ['file' => realUpload('baru.jpg', imageBytes(100, 100))])->assertCreated();

    expect(Storage::disk('local')->exists($first->photo_path))->toBeFalse()
        ->and(Storage::disk('local')->exists($first->thumbnailPath()))->toBeFalse()
        ->and($product->fresh()->photo_path)->not->toBe($first->photo_path);
});

test('foto produk hanya menerima gambar', function () {
    $product = Product::factory()->for($this->organization)->create();

    $this->actingAs($this->owner)
        ->postJson("/foto-produk/{$product->id}", ['file' => realUpload('katalog.pdf', '%PDF-1.4')])
        ->assertJsonPath('errors.file.0', 'Tipe file tidak didukung. Gunakan JPG, PNG, WebP, atau foto iPhone (HEIC).');
});

test('foto produk publik bisa dilihat tamu, foto produk tidak publik tidak', function () {
    $public = Product::factory()->for($this->organization)->create(['is_public' => true, 'photo_path' => 'products/1/a.jpg']);
    $private = Product::factory()->for($this->organization)->create(['is_public' => false, 'photo_path' => 'products/2/b.jpg']);
    foreach (['products/1/a.jpg', 'products/1/a-kecil.jpg', 'products/2/b.jpg'] as $path) {
        Storage::disk('local')->put($path, imageBytes(10, 10));
    }

    $response = $this->get("/foto-produk/{$public->id}?ukuran=kecil")->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('public');
    $this->get("/foto-produk/{$private->id}")->assertNotFound();

    $this->actingAs($this->owner)->get("/foto-produk/{$private->id}")->assertOk();
    $this->actingAs(User::factory()->kelompok($this->other)->create())->get("/foto-produk/{$private->id}")->assertNotFound();
});

test('foto produk kelompok yang tidak tampil publik tidak bisa dilihat tamu', function () {
    $this->organization->update(['is_public' => false]);
    $product = Product::factory()->for($this->organization)->create(['is_public' => true, 'photo_path' => 'products/1/a.jpg']);
    Storage::disk('local')->put('products/1/a.jpg', imageBytes(10, 10));

    $this->get("/foto-produk/{$product->id}")->assertNotFound();
});

test('OPD non-binaan tidak bisa mengunggah atau menghapus foto produk', function () {
    $product = Product::factory()->for($this->organization)->create();
    $this->actingAs(User::factory()->opd()->create());

    $this->postJson("/foto-produk/{$product->id}", ['file' => realUpload('a.jpg', imageBytes(10, 10))])->assertNotFound();
    $this->delete("/foto-produk/{$product->id}")->assertNotFound();
});

test('pemilik menghapus foto produk', function () {
    $product = Product::factory()->for($this->organization)->create(['photo_path' => 'products/1/a.jpg']);
    Storage::disk('local')->put('products/1/a.jpg', 'foto');
    Storage::disk('local')->put('products/1/a-kecil.jpg', 'foto');

    $this->actingAs($this->owner)->delete("/foto-produk/{$product->id}")->assertSessionHas('status', 'Foto produk dihapus.');

    expect($product->fresh()->photo_path)->toBeNull()->and(Storage::disk('local')->allFiles())->toBe([]);
});
