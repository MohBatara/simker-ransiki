<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ConvertsHeicUploads;
use App\Rules\AllowedUpload;
use App\Support\ImageUpload;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Satu kartu "Mengenal Distrik Ransiki". Isian berawalan "kartu_{n}[...]" agar error tidak tercampur antarform.
 * Foto opsional, aturan seperti foto jenis kelembagaan (lebar minimal 800 px).
 */
class AboutCardRequest extends FormRequest
{
    use ConvertsHeicUploads;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public static function prefixFor(int $n): string
    {
        return "kartu_{$n}";
    }

    public function prefix(): string
    {
        return self::prefixFor((int) $this->route('n'));
    }

    public function rules(): array
    {
        $p = $this->prefix();

        return [
            $p => ['required', 'array'],
            "{$p}.title" => ['nullable', 'string', 'max:60'],
            "{$p}.text" => ['nullable', 'string', 'max:90'],
            "{$p}.sector" => ['nullable', 'integer', Rule::exists('business_sectors', 'id')],
            "{$p}.photo" => ['nullable', 'bail', 'file', ImageUpload::notHeic(), 'max:'.ImageUpload::MAX_KILOBYTES, 'mimetypes:image/jpeg,image/png,image/webp', $this->dimensions(...)],
        ];
    }

    private function dimensions(string $attribute, UploadedFile $file, Closure $fail): void
    {
        $size = @getimagesize($file->getRealPath());

        if (! $size) {
            $fail('Foto tidak bisa dibaca. Simpan ulang sebagai JPG lalu coba lagi.');
        } elseif (max($size[0], $size[1]) > AllowedUpload::MAX_DIMENSION) {
            $fail('Foto terlalu besar: sisi terpanjang paling besar 8.000 px. Perkecil foto lalu coba lagi.');
        } elseif ($size[0] < InstitutionTypePhotoRequest::MIN_WIDTH) {
            $fail("Foto terlalu kecil: lebarnya {$size[0]} px, minimal 800 px. Pakai foto asli dari kamera, bukan hasil kiriman WhatsApp.");
        }
    }

    public function messages(): array
    {
        $p = $this->prefix();

        return [
            "{$p}.photo.max" => ImageUpload::MAX_MESSAGE,
            "{$p}.photo.mimetypes" => 'Foto harus JPG, PNG, WebP, atau foto iPhone (HEIC).',
        ];
    }

    public function attributes(): array
    {
        $p = $this->prefix();

        return ["{$p}.title" => 'judul', "{$p}.text" => 'kalimat', "{$p}.sector" => 'bidang usaha', "{$p}.photo" => 'foto'];
    }
}
