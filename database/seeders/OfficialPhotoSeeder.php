<?php

namespace Database\Seeders;

use App\Enums\HeroFocus;
use App\Models\HeroSlide;
use App\Models\SiteSetting;
use App\Support\SitePhotos;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Foto resmi distrik dari klien (database/seeders/media, keterangan di daftar-foto.csv): slide hero beranda dan foto
 * Profil Ransiki. Disimpan lewat jalur yang sama dengan unggahan Admin (SitePhotos, disk local, tabel hero_slides dan
 * site_settings), jadi Admin tetap bisa mengganti, mengurutkan, atau menghapusnya. Dipakai ProductionSeeder dan
 * DemoSeeder. Aman dijalankan ulang: slot yang sudah berisi (termasuk isian Admin) tidak pernah ditimpa.
 */
class OfficialPhotoSeeder extends Seeder
{
    /** Foto Profil Ransiki: kunci pengaturan => file di media/. Kantor distrik = foto utama (banner) dan foto kantor. */
    public const PROFILE = [
        'profile_photo_path' => 'profil/kantor-distrik.jpg',
        'office_photo_path' => 'profil/kantor-distrik.jpg',
        'potential_1_photo_path' => 'profil/pasar-buah-sayur.jpg',
        'potential_2_photo_path' => 'profil/nelayan-hasil-tangkapan.jpg',
        'potential_3_photo_path' => 'profil/pasar-ransiki-depan.jpg',
    ];

    public function run(): void
    {
        if (! config('simker.seed_photos')) {
            return;
        }

        $captions = self::captions();

        if (! HeroSlide::exists()) {
            foreach (self::heroFiles() as $position => $file) {
                $slide = new HeroSlide([
                    'caption' => $captions[$file],
                    'alt_text' => $captions[$file],
                    'focus' => HeroFocus::Center,
                    'is_active' => true,
                ]);
                $slide->forceFill(['photo_path' => SitePhotos::storeHero(self::path($file)), 'position' => $position + 1])->save();
            }
        }

        $settings = SiteSetting::values();
        $stored = [];

        foreach (self::PROFILE as $key => $file) {
            if (filled($settings[$key])) {
                continue;
            }

            // Satu file untuk banner dan foto kantor tetap disimpan sebagai dua salinan agar menghapus salah satu
            // di Konten Website tidak merusak yang lain.
            $stored[$key] = match (true) {
                $key === 'profile_photo_path' => SitePhotos::storeSlot('profil', self::path($file)),
                $key === 'office_photo_path' => SitePhotos::storeSlot('kantor', self::path($file)),
                default => SitePhotos::storePotential((int) filter_var($key, FILTER_SANITIZE_NUMBER_INT), self::path($file)),
            };
        }

        if ($stored) {
            SiteSetting::put($stored);
        }
    }

    /** media/hero/*.jpg urut nama file (maks HeroSlide::MAX). */
    public static function heroFiles(): array
    {
        $files = array_map(fn (string $path) => 'hero/'.basename($path), glob(self::path('hero/*.jpg')) ?: []);
        sort($files);

        return array_slice($files, 0, HeroSlide::MAX);
    }

    /** @return array<string, string> file => keterangan dari daftar-foto.csv */
    public static function captions(): array
    {
        $handle = fopen(self::path('daftar-foto.csv'), 'r') ?: throw new RuntimeException('daftar-foto.csv tidak ditemukan.');
        $header = fgetcsv($handle, escape: '');
        $captions = [];

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $row = array_combine($header, $row);
            $captions[$row['file']] = trim($row['keterangan']);
        }

        fclose($handle);

        return $captions;
    }

    public static function path(string $file): string
    {
        return database_path('seeders/media/'.$file);
    }
}
