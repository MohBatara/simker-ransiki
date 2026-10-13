<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Isi website publik yang diatur Admin di menu Konten Website.
 */
#[Fillable(['key', 'value'])]
class SiteSetting extends Model
{
    /** key => label; urutan ini juga urutan di form Konten Website. */
    public const KEYS = [
        'hero_title' => 'Judul beranda',
        'hero_title_accent' => 'Bagian judul berwarna',
        'home_intro' => 'Pengantar beranda',
        'about_title' => 'Judul bagian Tentang',
        ...self::ABOUT_CARD_KEYS,
        'how_title' => 'Judul bagian Cara kerja',
        'how_1_title' => 'Langkah 1: judul',
        'how_1_text' => 'Langkah 1: penjelasan',
        'how_2_title' => 'Langkah 2: judul',
        'how_2_text' => 'Langkah 2: penjelasan',
        'how_3_title' => 'Langkah 3: judul',
        'how_3_text' => 'Langkah 3: penjelasan',
        'how_4_title' => 'Langkah 4: judul',
        'how_4_text' => 'Langkah 4: penjelasan',
        'profile_body' => 'Profil Ransiki',
        'economic_potential' => 'Potensi ekonomi',
        'office_address' => 'Alamat kantor',
        'office_phone' => 'Telepon kantor',
        'office_whatsapp' => 'WhatsApp kantor',
        'office_email' => 'Email kantor',
        'office_hours' => 'Jam layanan',
        'cover_caption' => 'Keterangan foto sampul',
        'cover_credit' => 'Nama fotografer',
        'cover_photo_path' => 'Foto sampul',
        'about_photo_path' => 'Foto bagian Tentang',
    ];

    /** Nilai bawaan bila Admin belum mengisi (atau mengosongkan) kuncinya. */
    public const DEFAULTS = [
        'hero_title' => 'Kelembagaan Ekonomi',
        'hero_title_accent' => 'Distrik Ransiki',
        'home_intro' => 'Profil kelompok usaha, produk unggulan, dan kontak kelembagaan ekonomi dari berbagai kampung di Distrik Ransiki.',
        'about_title' => 'Mengenal Distrik Ransiki',
        'about_card_1_title' => 'Kebun kakao dan kopi',
        'about_card_1_text' => 'Kelompok pekebun merawat kakao, kopi, dan pinang di sekitar kampung.',
        'about_card_2_title' => 'Laut dan perikanan',
        'about_card_2_text' => 'Nelayan dan petambak menyediakan ikan segar dan hasil laut olahan.',
        'about_card_3_title' => 'Olahan rumahan',
        'about_card_3_text' => 'Usaha warga mengolah hasil kebun dan laut menjadi pangan siap jual.',
        'how_title' => 'Cara kerja SIM-KER',
        'how_1_title' => 'Didaftarkan distrik',
        'how_1_text' => 'Kantor distrik mendata kelompok dan membuatkan akun.',
        'how_2_title' => 'Melengkapi profil',
        'how_2_text' => 'Kelompok mengisi profil, produk, dan izin usaha.',
        'how_3_title' => 'Melapor tiap bulan',
        'how_3_text' => 'Kelompok mengirim laporan kegiatan dan hasil usaha.',
        'how_4_title' => 'Diverifikasi OPD',
        'how_4_text' => 'Dinas terkait memeriksa laporan sebelum menjadi data resmi.',
    ];

    /** Tiga kartu foto bagian "Mengenal Distrik Ransiki" (Konten Website > Beranda): judul, kalimat, bidang, foto. */
    public const ABOUT_CARD_KEYS = [
        'about_card_1_title' => 'Kartu 1: judul', 'about_card_1_text' => 'Kartu 1: kalimat', 'about_card_1_sector' => 'Kartu 1: bidang usaha', 'about_card_1_photo_path' => 'Kartu 1: foto',
        'about_card_2_title' => 'Kartu 2: judul', 'about_card_2_text' => 'Kartu 2: kalimat', 'about_card_2_sector' => 'Kartu 2: bidang usaha', 'about_card_2_photo_path' => 'Kartu 2: foto',
        'about_card_3_title' => 'Kartu 3: judul', 'about_card_3_text' => 'Kartu 3: kalimat', 'about_card_3_sector' => 'Kartu 3: bidang usaha', 'about_card_3_photo_path' => 'Kartu 3: foto',
    ];

    /** Bidang usaha bawaan tiap kartu (dicocokkan dengan nama di Data Master) selama Admin belum memilih. */
    public const ABOUT_CARD_SECTORS = [1 => 'Perkebunan', 2 => 'Perikanan', 3 => 'Pengolahan Hasil'];

    /** Slot foto lebar di URL (/foto-sampul, /foto-tentang) => kunci pengaturan. */
    public const PHOTO_SLOTS = [
        'sampul' => 'cover_photo_path',
        'tentang' => 'about_photo_path',
    ];

    public const CACHE_KEY = 'site_settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Semua pengaturan (kosong = null), di-cache sampai disimpan ulang.
     *
     * @return array<string, ?string>
     */
    public static function values(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return collect(self::KEYS)->map(fn ($label, string $key) => filled($stored[$key] ?? null) ? $stored[$key] : null)->all();
    }

    public static function get(string $key): ?string
    {
        return static::values()[$key] ?? null;
    }

    /** @param array<string, ?string> $values */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
        Cache::forget(\App\Support\PublicDirectory::FOOTER_CACHE_KEY);
    }
}
