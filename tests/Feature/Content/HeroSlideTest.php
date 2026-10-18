<?php

use App\Models\HeroSlide;
use App\Models\Organization;
use App\Models\User;
use App\Support\ImageVariants;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
});

function slideInput(array $overrides = [], string $prefix = 'baru'): array
{
    return [$prefix => [
        'photo' => realUpload('hero.jpg', jpegWithExif(1800, 900, orientation: 1)),
        'alt_text' => 'Nelayan menarik jaring di perahu kayu',
        'caption' => 'Bidang usaha perikanan',
        'focus' => 'bottom',
        'is_active' => '1',
        ...$overrides,
    ]];
}

test('admin menambah slide: varian WebP 640/1280/1920 + JPEG tanpa EXIF, file asli tidak disimpan', function () {
    $this->actingAs($this->admin)->post('/admin/website/slide', slideInput())
        ->assertRedirect(route('admin.content.edit'))
        ->assertSessionHas('status', 'Slide ditambahkan.');

    $slide = HeroSlide::sole();
    expect($slide->only(['alt_text', 'caption', 'is_active', 'position']))
        ->toBe(['alt_text' => 'Nelayan menarik jaring di perahu kayu', 'caption' => 'Bidang usaha perikanan', 'is_active' => true, 'position' => 1])
        ->and($slide->focus->value)->toBe('bottom')
        ->and($slide->created_by)->toBe($this->admin->id);

    $files = Storage::disk('local')->allFiles('site');
    expect($files)->toHaveCount(4);
    foreach (ImageVariants::HERO as $width) {
        $variant = Storage::disk('local')->get(ImageVariants::path($slide->photo_path, $width));
        expect(getimagesizefromstring($variant)['mime'])->toBe('image/webp')
            ->and(getimagesizefromstring($variant)[0])->toBe(min($width, 1800));
    }
    expect(exifOf(Storage::disk('local')->get($slide->photo_path)))->not->toHaveKey('GPSLatitudeRef');
});

test('validasi: foto wajib, lebar minimal 1280 px, tipe, ukuran, teks alternatif wajib', function () {
    $this->actingAs($this->admin);

    $this->post('/admin/website/slide', slideInput(['photo' => realUpload('kecil.jpg', imageBytes(1279, 800))]))
        ->assertSessionHasErrors(['baru.photo' => 'Foto terlalu kecil: lebarnya 1279 px, minimal 1.280 px. Pakai foto asli dari kamera, bukan hasil kiriman WhatsApp.']);
    $this->post('/admin/website/slide', slideInput(['photo' => realUpload('dok.pdf', "%PDF-1.4\n%contoh")]))
        ->assertSessionHasErrors('baru.photo');
    $this->post('/admin/website/slide', slideInput(['photo' => Illuminate\Http\UploadedFile::fake()->create('besar.jpg', 9000, 'image/jpeg')]))
        ->assertSessionHasErrors(['baru.photo' => 'Ukuran foto paling besar 8 MB. Perkecil foto lalu coba lagi.']);
    $this->post('/admin/website/slide', slideInput(['photo' => null]))
        ->assertSessionHasErrors(['baru.photo' => 'Pilih foto slide.']);
    $this->post('/admin/website/slide', slideInput(['alt_text' => '']))
        ->assertSessionHasErrors(['baru.alt_text' => 'Isi teks alternatif: jelaskan isi foto untuk pembaca layar.']);

    expect(HeroSlide::count())->toBe(0);
});

test('PNG diterima dan maksimal 3 slide', function () {
    $this->actingAs($this->admin);
    HeroSlide::factory()->count(2)->create();

    $this->post('/admin/website/slide', slideInput(['photo' => realUpload('hero.png', imageBytes(1600, 900, 'png'))]))->assertSessionHasNoErrors();
    $this->post('/admin/website/slide', slideInput())
        ->assertSessionHasErrors(['baru.photo' => 'Sudah ada 3 slide. Hapus salah satu slide lebih dulu.']);

    expect(HeroSlide::count())->toBe(3);
});

test('admin mengubah slide tanpa mengganti foto, lalu mengganti fotonya (varian lama dihapus)', function () {
    $this->actingAs($this->admin);
    $this->post('/admin/website/slide', slideInput());
    $slide = HeroSlide::sole();
    $oldPath = $slide->photo_path;
    $prefix = 'slide_'.$slide->id;

    $this->post("/admin/website/slide/{$slide->id}", slideInput(['photo' => null, 'alt_text' => 'Alt baru', 'is_active' => null, 'focus' => 'top'], $prefix))
        ->assertSessionHas('status', 'Slide disimpan.');
    expect($slide->fresh()->only(['alt_text', 'is_active', 'photo_path']))->toBe(['alt_text' => 'Alt baru', 'is_active' => false, 'photo_path' => $oldPath]);

    $this->post("/admin/website/slide/{$slide->id}", slideInput([], $prefix))->assertSessionHasNoErrors();
    expect($slide->fresh()->photo_path)->not->toBe($oldPath)
        ->and(Storage::disk('local')->exists($oldPath))->toBeFalse()
        ->and(Storage::disk('local')->exists(ImageVariants::path($oldPath, 1920)))->toBeFalse();

    // Error form ubah memakai awalan slide itu, tidak tercampur dengan form tambah.
    $this->post("/admin/website/slide/{$slide->id}", slideInput(['alt_text' => ''], $prefix))
        ->assertSessionHasErrors("{$prefix}.alt_text")->assertSessionDoesntHaveErrors('baru.alt_text');
});

test('urutan naik/turun dan hapus slide (file ikut terhapus, urutan dirapikan)', function () {
    $this->actingAs($this->admin);
    [$a, $b, $c] = HeroSlide::factory()->count(3)->sequence(['position' => 1], ['position' => 2], ['position' => 3])->create();
    Storage::disk('local')->put($b->photo_path, 'x');

    $this->patch("/admin/website/slide/{$c->id}/naik")->assertSessionHas('status', 'Urutan slide diubah.');
    expect(HeroSlide::orderBy('position')->pluck('id')->all())->toBe([$a->id, $c->id, $b->id]);

    $this->patch("/admin/website/slide/{$a->id}/naik");
    expect(HeroSlide::orderBy('position')->pluck('id')->all())->toBe([$a->id, $c->id, $b->id]);

    $this->delete("/admin/website/slide/{$c->id}")->assertSessionHas('status', 'Slide dihapus.');
    expect(HeroSlide::orderBy('position')->pluck('position', 'id')->all())->toBe([$a->id => 1, $b->id => 2]);

    $this->delete("/admin/website/slide/{$b->id}");
    expect(Storage::disk('local')->exists($b->photo_path))->toBeFalse();
});

test('hanya Admin yang bisa mengelola slide; tamu diarahkan ke halaman masuk', function (string $role) {
    $slide = HeroSlide::factory()->create();
    $user = match ($role) {
        'opd' => User::factory()->opd()->create(),
        'kelompok' => User::factory()->kelompok(Organization::factory()->create())->create(),
    };
    $this->actingAs($user);

    $this->get('/admin/website')->assertForbidden();
    $this->post('/admin/website/slide', slideInput())->assertForbidden();
    $this->post("/admin/website/slide/{$slide->id}", slideInput([], 'slide_'.$slide->id))->assertForbidden();
    $this->patch("/admin/website/slide/{$slide->id}/turun")->assertForbidden();
    $this->delete("/admin/website/slide/{$slide->id}")->assertForbidden();

    expect(HeroSlide::count())->toBe(1);
})->with(['opd', 'kelompok']);

test('policy slide hanya mengizinkan Admin', function () {
    $slide = HeroSlide::factory()->create();

    expect($this->admin->can('create', HeroSlide::class))->toBeTrue()
        ->and($this->admin->can('update', $slide))->toBeTrue()
        ->and(User::factory()->opd()->create()->can('update', $slide))->toBeFalse()
        ->and(User::factory()->opd()->create()->can('create', HeroSlide::class))->toBeFalse();

    $this->post('/logout');
    $this->post('/admin/website/slide', slideInput())->assertRedirect(route('login'));
});

test('foto slide nonaktif hanya bisa dibuka Admin (pratinjau); slide aktif publik', function () {
    $active = HeroSlide::factory()->create();
    $hidden = HeroSlide::factory()->inactive()->create();
    foreach ([$active, $hidden] as $slide) {
        Storage::disk('local')->put($slide->photo_path, imageBytes(20, 10));
    }

    $this->get("/foto-hero/{$active->id}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get("/foto-hero/{$hidden->id}")->assertNotFound();
    $this->actingAs(User::factory()->opd()->create())->get("/foto-hero/{$hidden->id}")->assertNotFound();
    $this->actingAs($this->admin)->get("/foto-hero/{$hidden->id}")->assertOk();
});

test('tab Beranda menampilkan slide, form tambah, dan judul; tab Halaman & kontak berisi kontak', function () {
    HeroSlide::factory()->create(['caption' => 'Keterangan slide contoh']);

    $this->actingAs($this->admin)->get('/admin/website')->assertOk()
        ->assertSee('Foto hero')->assertSee('Keterangan slide contoh')->assertSee('Tambah slide')
        ->assertSee('Pratinjau')->assertSee('Kelembagaan Ekonomi')->assertSee('Halaman &amp; kontak', false);
    $this->get('/admin/website/halaman')->assertOk()->assertSee('Kontak kantor distrik')->assertSee('Foto sampul');

    HeroSlide::factory()->count(2)->create();
    $this->get('/admin/website')->assertDontSee('slide lagi.')->assertSee('Sudah 3 slide.');
});

test('foto 1280–1599 px diterima dengan peringatan kuning; 1600 px ke atas tanpa peringatan', function () {
    $this->actingAs($this->admin);

    $this->post('/admin/website/slide', slideInput(['photo' => realUpload('pantai.png', imageBytes(1532, 1026, 'png'))]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Slide ditambahkan.')
        ->assertSessionHas('warning', 'Foto mungkin kurang tajam di layar lebar (lebarnya 1532 px). Bila ada, ganti dengan foto selebar minimal 1.600 px.');
    $this->get('/admin/website')->assertSee('Foto mungkin kurang tajam di layar lebar')->assertSee('bg-wait-bg', false);

    $this->post('/admin/website/slide', slideInput(['photo' => realUpload('batas.jpg', imageBytes(1280, 720))]))->assertSessionHas('warning');
    $this->post('/admin/website/slide', slideInput(['photo' => realUpload('tajam.jpg', imageBytes(1600, 900))]))
        ->assertSessionHasNoErrors()->assertSessionMissing('warning');

    expect(HeroSlide::count())->toBe(3);
});

test('validasi gagal: ringkasan error tampil di atas tombol simpan form itu dan isian lain tetap terisi', function () {
    $this->actingAs($this->admin)->from('/admin/website')
        ->post('/admin/website/slide', slideInput(['photo' => realUpload('kecil.jpg', imageBytes(1000, 600)), 'caption' => 'Keterangan yang diketik', 'focus' => 'bottom']))
        ->assertRedirect('/admin/website');

    $html = $this->get('/admin/website')->assertOk()->getContent();
    $summary = strpos($html, 'Slide belum disimpan. Ada 1 isian yang perlu diperbaiki');
    $button = strpos($html, 'Tambah slide</', $summary ?: 0);

    expect($summary)->not->toBeFalse()
        ->and($button)->toBeGreaterThan($summary)
        ->and($html)->toContain('data-form-errors')
        ->toContain('Foto terlalu kecil: lebarnya 1000 px, minimal 1.280 px.')
        ->toContain('Pilih ulang fotonya')
        ->toContain('value="Nelayan menarik jaring di perahu kayu"')
        ->toContain('value="Keterangan yang diketik"')
        ->toMatch('/<option value="bottom"[^>]*selected/');
});

test('slide baru dan judul baru langsung tampil di beranda (tidak tertahan cache)', function () {
    $this->get('/')->assertOk()->assertSee('/foto-hero/bawaan', false);

    $this->actingAs($this->admin)->post('/admin/website/slide', slideInput());
    $this->put('/admin/website', ['hero_title' => 'Judul Baru', 'hero_title_accent' => 'Ransiki']);
    $slide = HeroSlide::sole();

    $html = $this->get('/')->getContent();
    preg_match('#<section[^>]*data-hero.*?</section>#s', $html, $hero);
    expect($hero[0])->toContain("/foto-hero/{$slide->id}?ukuran=1920&amp;format=webp")->toContain('Judul Baru')->not->toContain('/foto-hero/bawaan');

    // Setiap URL di srcset bisa dibuka tanpa login dan berupa WebP; cadangan JPEG juga.
    $this->post('/logout');
    foreach (App\Support\ImageVariants::HERO as $width) {
        $this->get("/foto-hero/{$slide->id}?ukuran={$width}&format=webp")->assertOk()->assertHeader('Content-Type', 'image/webp');
    }
    $this->get("/foto-hero/{$slide->id}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});
