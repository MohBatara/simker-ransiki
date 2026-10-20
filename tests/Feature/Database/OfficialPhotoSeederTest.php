<?php

use App\Models\HeroSlide;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\DemoData;
use Database\Seeders\OfficialPhotoSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Support\Facades\Storage;

test('daftar-foto.csv memuat keterangan untuk setiap foto di media, dan setiap file yang dipakai ada', function () {
    $captions = OfficialPhotoSeeder::captions();
    $files = collect(glob(OfficialPhotoSeeder::path('*/*.jpg')))->map(fn ($path) => basename(dirname($path)).'/'.basename($path));

    expect($files->diff(array_keys($captions))->all())->toBe([])
        ->and(collect($captions)->every(fn ($caption) => filled($caption) && mb_strlen($caption) <= 100))->toBeTrue()
        ->and(OfficialPhotoSeeder::heroFiles())->toBe(['hero/01-kantor-distrik-ransiki.jpg', 'hero/02-pasar-ransiki.jpg', 'hero/03-nelayan-ransiki.jpg', 'hero/04-hasil-kebun.jpg'])
        ->and(collect(OfficialPhotoSeeder::PROFILE)->every(fn ($file) => is_file(OfficialPhotoSeeder::path($file))))->toBeTrue();

    // Foto produk, kelompok, dan berita contoh hanya dari media yang ada; mahkota adat belum boleh dipakai.
    $demo = collect(DemoData::ORGANIZATIONS)->flatMap(fn ($data) => [...array_column($data['products'], 5), ...($data['photos'] ?? [])])
        ->filter(fn ($file) => str_starts_with((string) $file, 'media/'));
    expect($demo)->not->toBeEmpty()
        ->and($demo->every(fn ($file) => is_file(database_path('seeders/'.$file))))->toBeTrue()
        ->and($demo->filter(fn ($file) => str_contains($file, 'mahkota'))->all())->toBe([])
        ->and(file_get_contents(database_path('seeders/DemoSeeder.php')))->not->toContain('mahkota');
});

test('tes tidak memasang foto resmi kecuali diaktifkan', function () {
    $this->seed(ProductionSeeder::class);

    expect(HeroSlide::count())->toBe(0)->and(SiteSetting::get('profile_photo_path'))->toBeNull();
});

test('seeder produksi memasang 4 slide hero dan foto Profil Ransiki lewat jalur unggahan Admin, aman dijalankan ulang', function () {
    Storage::fake('local');
    config(['simker.seed_photos' => true]);

    $this->seed(ProductionSeeder::class);

    $slides = HeroSlide::orderBy('position')->get();
    expect($slides->pluck('caption')->all())->toBe(['Kantor Distrik Ransiki', 'Pedagang hasil kebun di Pasar Ransiki', 'Nelayan menarik perahu ke pantai', 'Hasil kebun warga di pasar'])
        ->and($slides->pluck('position')->all())->toBe([1, 2, 3, 4])
        ->and($slides->every(fn (HeroSlide $slide) => $slide->is_active && $slide->alt_text === $slide->caption))->toBeTrue()
        ->and($slides->every(fn (HeroSlide $slide) => str_starts_with($slide->photo_path, 'site/hero-') && Storage::disk('local')->exists($slide->photo_path)))->toBeTrue();

    foreach (array_keys(OfficialPhotoSeeder::PROFILE) as $key) {
        expect(Storage::disk('local')->exists(SiteSetting::get($key)))->toBeTrue();
    }

    // Banner dan foto kantor dari file yang sama, tetapi disimpan terpisah agar bisa dihapus sendiri-sendiri.
    expect(SiteSetting::get('profile_photo_path'))->not->toBe(SiteSetting::get('office_photo_path'));

    // Isian Admin tidak ditimpa dan slide tidak dobel saat seeder dijalankan ulang.
    SiteSetting::put(['potential_1_photo_path' => 'site/potensi-1-admin.jpg']);
    $this->seed(ProductionSeeder::class);
    expect(HeroSlide::count())->toBe(4)
        ->and(SiteSetting::get('potential_1_photo_path'))->toBe('site/potensi-1-admin.jpg');

    // Tampil di beranda dan Profil Ransiki lewat route foto publik; Admin tetap bisa mengurutkan.
    $this->get('/')->assertOk()->assertSee('Nelayan menarik perahu ke pantai')->assertSee(route('public.hero-photo', $slides[3]), false);
    $this->get('/profil')->assertOk()->assertSee('/foto-profil', false)->assertSee('/foto-kantor', false);
    $this->get(route('public.hero-photo', $slides[0]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/admin/website')->assertOk()->assertSee('Sudah 4 slide.');
});
