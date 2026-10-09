<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Tipe file dicek dari isinya (finfo), bukan dari ekstensi. Gambar raksasa ditolak sebelum didekode.
 */
class AllowedUpload implements ValidationRule
{
    public const IMAGE_TYPES = ['image/jpeg', 'image/png'];

    public const DOCUMENT_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

    public const MAX_DIMENSION = 8000;

    public const HEIC_MESSAGE = "Format HEIC belum didukung. Ubah pengaturan kamera iPhone ke 'Paling Kompatibel', atau kirim ulang sebagai JPG.";

    /**
     * @param  'photo'|'document'  $kind
     */
    public function __construct(private string $kind = 'document') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $mime = $value->getMimeType();
        $extension = strtolower($value->getClientOriginalExtension());

        if (in_array($extension, ['heic', 'heif'], true) || in_array($mime, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true)) {
            $fail(self::HEIC_MESSAGE);

            return;
        }

        $allowed = $this->kind === 'photo' ? self::IMAGE_TYPES : self::DOCUMENT_TYPES;
        if (! in_array($mime, $allowed, true)) {
            $fail($this->kind === 'photo'
                ? 'Tipe file tidak didukung. Gunakan foto JPG atau PNG.'
                : 'Tipe file tidak didukung. Gunakan JPG, PNG, atau PDF.');

            return;
        }

        if (in_array($mime, self::IMAGE_TYPES, true)) {
            $size = @getimagesize($value->getRealPath());

            if ($size === false) {
                $fail('File gambar rusak atau tidak bisa dibaca. Coba ambil ulang fotonya.');
            } elseif ($size[0] > self::MAX_DIMENSION || $size[1] > self::MAX_DIMENSION) {
                $fail('Gambar terlalu besar (lebih dari '.self::MAX_DIMENSION.' piksel per sisi). Perkecil gambar lalu coba lagi.');
            }
        }
    }
}
