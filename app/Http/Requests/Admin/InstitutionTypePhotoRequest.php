<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ConvertsHeicUploads;
use App\Rules\AllowedUpload;
use App\Support\ImageUpload;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Foto kartu jenis kelembagaan di beranda: aturan file seperti foto hero, lebar minimal 800 px.
 */
class InstitutionTypePhotoRequest extends FormRequest
{
    use ConvertsHeicUploads;

    public const MIN_WIDTH = 800;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'bail', 'file', ImageUpload::notHeic(), 'max:'.ImageUpload::MAX_KILOBYTES, 'mimetypes:image/jpeg,image/png,image/webp', $this->dimensions(...)],
        ];
    }

    private function dimensions(string $attribute, UploadedFile $file, Closure $fail): void
    {
        $size = @getimagesize($file->getRealPath());

        if (! $size) {
            $fail('Foto tidak bisa dibaca. Simpan ulang sebagai JPG lalu coba lagi.');
        } elseif (max($size[0], $size[1]) > AllowedUpload::MAX_DIMENSION) {
            $fail('Foto terlalu besar: sisi terpanjang paling besar 8.000 px. Perkecil foto lalu coba lagi.');
        } elseif ($size[0] < self::MIN_WIDTH) {
            $fail("Foto terlalu kecil: lebarnya {$size[0]} px, minimal 800 px. Pakai foto asli dari kamera, bukan hasil kiriman WhatsApp.");
        }
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Pilih foto lebih dulu.',
            'photo.uploaded' => 'Foto gagal diunggah. Periksa koneksi lalu coba lagi.',
            'photo.max' => ImageUpload::MAX_MESSAGE,
            'photo.mimetypes' => 'Foto harus JPG, PNG, WebP, atau foto iPhone (HEIC).',
        ];
    }
}
