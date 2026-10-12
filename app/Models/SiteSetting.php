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
    ];

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
