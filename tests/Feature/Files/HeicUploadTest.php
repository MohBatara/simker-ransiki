<?php

use App\Models\Attachment;
use App\Models\HeroSlide;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Foto iPhone (HEIC), WebP, dan pembersihan metadata di semua jalur unggah foto. Di browser HEIC diubah ke JPG
 * (resources/js/image-upload.js, tes di tests/js dan tests/Browser); di sini jalur server bila HEIC tetap terkirim.
 */
beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
    $this->organization = Organization::factory()->create();
    $this->product = Product::factory()->for($this->organization)->create();
});

function heicUpload(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'heic');
    copy(base_path('tests/fixtures/foto-iphone.heic'), $path);

    return new UploadedFile($path, 'IMG_0001.HEIC', null, null, true);
}

/** Imagick di mesin ini benar-benar bisa membaca HEIC (perlu dekoder HEVC, tidak selalu ada walau HEIC terdaftar). */
function serverDecodesHeic(): bool
{
    return ImageUpload::convertHeic(heicUpload()) !== null;
}

test('HEIC ditolak dengan pesan cara mengirim ulang bila server tidak bisa mengonversi, di setiap jalur unggah', function () {
    config(['simker.uploads.convert_heic' => false]);
    $this->actingAs($this->admin);

    $this->postJson("/lampiran/kelompok/{$this->organization->id}", ['file' => heicUpload()])
        ->assertUnprocessable()->assertJsonPath('errors.file.0', ImageUpload::HEIC_MESSAGE);
    $this->postJson("/foto-produk/{$this->product->id}", ['file' => heicUpload()])
        ->assertUnprocessable()->assertJsonPath('errors.file.0', ImageUpload::HEIC_MESSAGE);
    $this->postJson(route('admin.content.photo.store', 'profil'), ['file' => heicUpload()])
        ->assertUnprocessable()->assertJsonPath('errors.file.0', ImageUpload::HEIC_MESSAGE);
    $this->post('/admin/website/slide', ['baru' => ['photo' => heicUpload(), 'alt_text' => 'Pantai', 'focus' => 'center']])
        ->assertSessionHasErrors(['baru.photo' => ImageUpload::HEIC_MESSAGE]);

    expect(Attachment::count())->toBe(0)->and(HeroSlide::count())->toBe(0)
        ->and($this->product->fresh()->photo_path)->toBeNull();
});

test('HEIC yang tidak bisa dibaca Imagick tetap ditolak dengan pesan yang sama, tanpa error server', function () {
    $this->actingAs($this->admin);
    $broken = realUpload('rusak.heic', "\0\0\0\x18ftypheic\0\0\0\0mif1heic");

    $this->postJson("/lampiran/kelompok/{$this->organization->id}", ['file' => $broken])
        ->assertUnprocessable()->assertJsonPath('errors.file.0', ImageUpload::HEIC_MESSAGE);
});

test('HEIC dikonversi server menjadi JPG tanpa metadata bila Imagick bisa membacanya', function () {
    if (! serverDecodesHeic()) {
        $this->markTestSkipped('Imagick di mesin ini tidak punya dekoder HEIC (HEVC).');
    }
    $this->actingAs($this->admin);

    $this->postJson("/lampiran/kelompok/{$this->organization->id}", ['file' => heicUpload()])->assertCreated();

    $attachment = Attachment::firstOrFail();
    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($attachment->path));

    expect($attachment->mime_type)->toBe('image/jpeg')
        ->and($attachment->original_name)->toBe('IMG_0001.jpg')
        ->and(max($width, $height))->toBeLessThanOrEqual(ImageUpload::MAX_SIDE);
});

test('WebP diterima dan disimpan ulang sebagai JPG', function () {
    $image = imagecreatetruecolor(300, 200);
    ob_start();
    imagewebp($image);
    $webp = ob_get_clean();
    $this->actingAs($this->admin);

    $this->postJson("/lampiran/kelompok/{$this->organization->id}", ['file' => realUpload('foto.webp', $webp)])->assertCreated();
    $this->postJson("/foto-produk/{$this->product->id}", ['file' => realUpload('produk.webp', $webp)])->assertCreated();

    expect(Attachment::firstOrFail()->mime_type)->toBe('image/jpeg')
        ->and(getimagesizefromstring(Storage::disk('local')->get($this->product->fresh()->photo_path))['mime'])->toBe('image/jpeg');
});

test('JPG besar dikecilkan dan GPS dibuang di foto produk, foto Konten Website, dan slide hero', function () {
    $this->actingAs($this->admin);

    $this->postJson("/foto-produk/{$this->product->id}", ['file' => realUpload('produk.jpg', jpegWithExif(3000, 2400, orientation: 1))])->assertCreated();
    $this->postJson(route('admin.content.photo.store', 'profil'), ['file' => realUpload('profil.jpg', jpegWithExif(3200, 2000, orientation: 1))])->assertCreated();
    $this->post('/admin/website/slide', ['baru' => [
        'photo' => realUpload('hero.jpg', jpegWithExif(3000, 1500, orientation: 1)), 'alt_text' => 'Pantai', 'focus' => 'center', 'is_active' => '1',
    ]])->assertSessionHasNoErrors();

    foreach ([$this->product->fresh()->photo_path, SiteSetting::get('profile_photo_path'), HeroSlide::firstOrFail()->photo_path] as $path) {
        $stored = Storage::disk('local')->get($path);
        [$width, $height] = getimagesizefromstring($stored);

        expect(max($width, $height))->toBeLessThanOrEqual(ImageUpload::MAX_SIDE)
            ->and(exifOf($stored))->not->toHaveKey('GPSLatitudeRef');
    }
});

test('aturan hero tetap: di bawah 1280 px ditolak, 1280 sampai 1599 px diterima dengan peringatan', function () {
    $this->actingAs($this->admin);
    $slide = fn (int $width) => ['baru' => ['photo' => realUpload('hero.jpg', imageBytes($width, 700)), 'alt_text' => 'Pantai', 'focus' => 'center']];

    $this->post('/admin/website/slide', $slide(1200))->assertSessionHasErrors('baru.photo');
    $this->post('/admin/website/slide', $slide(1400))->assertSessionHasNoErrors()->assertSessionHas('warning');
});

test('kotak unggah: accept bawaan tanpa HEIC, ditandai untuk accept per perangkat, teks bantuan menyebut HEIC', function () {
    $this->actingAs($this->admin);

    $this->get('/admin/website')->assertOk()
        ->assertSee('accept="'.ImageUpload::ACCEPT.'"', false)
        ->assertSee('data-image-accept="photo"', false)
        // x-on:change pemanggil (pratinjau slide) digabung dengan milik kotak unggah, bukan atribut ganda.
        ->assertSee('x-on:change="changed($event); pick($event)"', false)
        ->assertSee('JPG, PNG, WebP, atau foto iPhone (HEIC). Maksimal 10 MB.')
        ->assertDontSee('image/heic');
    $this->get("/admin/kelompok/{$this->organization->id}/produk")->assertOk()
        ->assertSee('data-image-accept="photo"', false);
    $this->get("/admin/kelompok/{$this->organization->id}")->assertOk()
        ->assertSee('data-image-accept="document"', false)
        ->assertSee('JPG, PNG, WebP, foto iPhone (HEIC), atau PDF.');
});
