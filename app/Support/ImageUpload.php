<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Imagick;
use Throwable;

/**
 * Aturan bersama semua unggahan foto (foto kelompok, produk, berita, hero, Profil Ransiki, lampiran gambar): accept,
 * teks bantuan, batas ukuran, dan foto iPhone (HEIC/HEIF). Di browser HEIC diubah ke JPG (resources/js/image-upload.js);
 * bila tetap sampai ke server (JavaScript mati), dicoba dikonversi dengan Imagick bila mendukung HEIC, selain itu ditolak.
 */
class ImageUpload
{
    /** Bawaan dari server (tanpa JavaScript): tanpa HEIC agar iPhone mengubah foto ke JPG sendiri. */
    public const ACCEPT = 'image/jpeg,image/png,image/webp';

    public const MAX_KILOBYTES = 10240;

    /** Sisi terpanjang foto HEIC hasil konversi server (sama dengan batas di browser). */
    public const MAX_SIDE = 2000;

    public const HINT = 'JPG, PNG, WebP, atau foto iPhone (HEIC). Maksimal 10 MB.';

    public const HEIC_MESSAGE = 'Foto ini tidak bisa diproses. Coba kirim ulang dari iPhone dengan format Paling Kompatibel (Pengaturan > Kamera > Format), atau ubah ke JPG dulu.';

    public const MAX_MESSAGE = 'Ukuran foto paling besar 10 MB. Perkecil foto lalu coba lagi.';

    /** Aturan validasi: tolak HEIC yang belum terkonversi (dipasang sebelum mimetypes agar pesannya jelas). */
    public static function notHeic(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value instanceof UploadedFile && self::isHeic($value)) {
                $fail(self::HEIC_MESSAGE);
            }
        };
    }

    public static function isHeic(UploadedFile $file): bool
    {
        return in_array(strtolower($file->getClientOriginalExtension()), ['heic', 'heif'], true)
            || in_array($file->getMimeType(), ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true);
    }

    /** Imagick terpasang dan bisa membaca HEIC (dimatikan lewat simker.uploads.convert_heic, mis. di tes). */
    public static function canConvertHeic(): bool
    {
        if (! config('simker.uploads.convert_heic', true) || ! class_exists(Imagick::class)) {
            return false;
        }

        try {
            return Imagick::queryFormats('HEIC') !== [];
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * HEIC → JPEG sementara (diputar sesuai EXIF, metadata dibuang, maks MAX_SIDE). Null bila tidak bisa; aturan
     * validasi lalu menolaknya dengan HEIC_MESSAGE.
     */
    public static function convertHeic(UploadedFile $file): ?UploadedFile
    {
        if (! self::canConvertHeic()) {
            return null;
        }

        try {
            $image = new Imagick($file->getRealPath());
            $image->setIteratorIndex(0);
            $image->autoOrient();
            $image->stripImage();
            if (max($image->getImageWidth(), $image->getImageHeight()) > self::MAX_SIDE) {
                $image->thumbnailImage(self::MAX_SIDE, self::MAX_SIDE, true);
            }
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(82);

            $path = tempnam(sys_get_temp_dir(), 'heic').'.jpg';
            $image->writeImage($path);
            $image->clear();
        } catch (Throwable) {
            return null;
        }

        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'foto';

        return new UploadedFile($path, $name.'.jpg', 'image/jpeg', null, true);
    }
}
