<?php

use App\Models\InstitutionType;
use App\Models\Organization;
use App\Models\User;
use App\Support\ImageVariants;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
    $this->type = InstitutionType::factory()->create(['name' => 'KUB']);
});

test('admin mengunggah foto jenis: varian WebP 640/1280/1920 + JPEG, foto lama dihapus saat diganti', function () {
    $this->actingAs($this->admin)->get("/admin/data-master/jenis-kelembagaan/{$this->type->id}/foto")->assertOk()->assertSee('Pratinjau');

    $this->post("/admin/data-master/jenis-kelembagaan/{$this->type->id}/foto", ['photo' => realUpload('kub.jpg', jpegWithExif(1000, 700, orientation: 1))])
        ->assertSessionHas('status', 'Foto jenis kelembagaan disimpan.');
    $first = $this->type->fresh()->photo_path;
    foreach (ImageVariants::HERO as $width) {
        expect(Storage::disk('local')->exists(ImageVariants::path($first, $width)))->toBeTrue();
    }
    expect(exifOf(Storage::disk('local')->get($first)))->not->toHaveKey('GPSLatitudeRef');

    $this->post("/admin/data-master/jenis-kelembagaan/{$this->type->id}/foto", ['photo' => realUpload('kub.png', imageBytes(900, 600, 'png'))])->assertSessionHasNoErrors();
    expect(Storage::disk('local')->exists($first))->toBeFalse()->and($this->type->fresh()->photo_path)->not->toBe($first);

    $this->post('/logout');
    $this->get("/foto-jenis/{$this->type->id}?ukuran=640&format=webp")->assertOk()->assertHeader('Content-Type', 'image/webp');

    $this->actingAs($this->admin)->delete("/admin/data-master/jenis-kelembagaan/{$this->type->id}/foto")->assertSessionHas('status', 'Foto jenis kelembagaan dihapus.');
    expect($this->type->fresh()->photo_path)->toBeNull()->and(Storage::disk('local')->allFiles('site'))->toBe([]);
    $this->get("/foto-jenis/{$this->type->id}")->assertNotFound();
});

test('foto jenis: lebar minimal 800 px, tipe dan ukuran dicek', function () {
    $this->actingAs($this->admin);
    $url = "/admin/data-master/jenis-kelembagaan/{$this->type->id}/foto";

    $this->post($url, ['photo' => realUpload('kecil.jpg', imageBytes(799, 500))])
        ->assertSessionHasErrors(['photo' => 'Foto terlalu kecil: lebarnya 799 px, minimal 800 px. Pakai foto asli dari kamera, bukan hasil kiriman WhatsApp.']);
    $this->post($url, ['photo' => realUpload('dok.pdf', "%PDF-1.4\n%contoh")])->assertSessionHasErrors('photo');
    $this->post($url, ['photo' => Illuminate\Http\UploadedFile::fake()->create('besar.jpg', 9000, 'image/jpeg')])
        ->assertSessionHasErrors(['photo' => 'Ukuran foto paling besar 8 MB. Perkecil foto lalu coba lagi.']);
    $this->post($url, [])->assertSessionHasErrors(['photo' => 'Pilih foto lebih dulu.']);

    expect($this->type->fresh()->photo_path)->toBeNull();
});

test('hanya Admin yang bisa mengelola foto jenis; tamu diarahkan ke halaman masuk', function (string $role) {
    $user = $role === 'opd' ? User::factory()->opd()->create() : User::factory()->kelompok(Organization::factory()->create())->create();
    $url = "/admin/data-master/jenis-kelembagaan/{$this->type->id}/foto";

    $this->actingAs($user)->get($url)->assertForbidden();
    $this->post($url, ['photo' => realUpload('kub.jpg', imageBytes(900, 600))])->assertForbidden();
    $this->delete($url)->assertForbidden();
    $this->post('/logout');
    $this->post($url, ['photo' => realUpload('kub.jpg', imageBytes(900, 600))])->assertRedirect(route('login'));

    expect($this->type->fresh()->photo_path)->toBeNull();
})->with(['opd', 'kelompok']);

test('deskripsi singkat jenis paling banyak 90 karakter', function () {
    $this->actingAs($this->admin)
        ->put("/admin/data-master/jenis-kelembagaan/{$this->type->id}", ['name' => 'KUB', 'code' => $this->type->code, 'description' => str_repeat('a', 91)])
        ->assertSessionHasErrors(['description' => 'Deskripsi singkat paling banyak 90 karakter.']);

    $this->get('/admin/data-master/jenis-kelembagaan')->assertSee('paling banyak 90 karakter')->assertSee('Foto');
});
