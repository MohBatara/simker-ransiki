<?php

namespace App\Http\Requests\Admin;

use App\Enums\HeroFocus;
use App\Http\Requests\Concerns\ConvertsHeicUploads;
use App\Models\HeroSlide;
use App\Rules\AllowedUpload;
use App\Support\ImageUpload;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tambah/ubah slide hero. Hak akses lewat HeroSlidePolicy di route. Isian setiap form slide berawalan
 * sendiri ("baru[...]" / "slide_{id}[...]") agar pesan error dan isian lama tidak muncul di form slide lain.
 */
class HeroSlideRequest extends FormRequest
{
    use ConvertsHeicUploads;

    public const MAX_KILOBYTES = ImageUpload::MAX_KILOBYTES;

    /** Di bawah ini ditolak. */
    public const MIN_WIDTH = 1280;

    /** Di bawah ini diterima dengan peringatan (hero tampil sampai 1920 px). */
    public const SHARP_WIDTH = 1600;

    public static function prefixFor(?HeroSlide $slide): string
    {
        return $slide ? 'slide_'.$slide->id : 'baru';
    }

    public function prefix(): string
    {
        return self::prefixFor($this->route('slide'));
    }

    public function rules(): array
    {
        $p = $this->prefix();

        return [
            $p => ['required', 'array'],
            "{$p}.photo" => [$this->route('slide') ? 'nullable' : 'required', 'bail', 'file', ImageUpload::notHeic(), 'max:'.self::MAX_KILOBYTES, 'mimetypes:image/jpeg,image/png,image/webp', $this->dimensions(...)],
            "{$p}.alt_text" => ['required', 'string', 'max:200'],
            "{$p}.caption" => ['nullable', 'string', 'max:100'],
            "{$p}.focus" => ['required', Rule::enum(HeroFocus::class)],
            "{$p}.is_active" => ['nullable', 'boolean'],
        ];
    }

    /** @return array{alt_text: string, caption: ?string, focus: string, is_active: bool} */
    public function fields(): array
    {
        $data = $this->validated()[$this->prefix()];

        return [
            'alt_text' => $data['alt_text'],
            'caption' => $data['caption'] ?? null,
            'focus' => $data['focus'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    public function photo(): ?UploadedFile
    {
        return $this->file($this->prefix().'.photo');
    }

    /** Peringatan untuk foto yang diterima tetapi kurang lebar (1280–1599 px); null bila cukup. */
    public function sharpnessWarning(): ?string
    {
        $width = $this->photo() ? (@getimagesize($this->photo()->getRealPath())[0] ?? null) : null;

        return $width && $width < self::SHARP_WIDTH
            ? "Foto mungkin kurang tajam di layar lebar (lebarnya {$width} px). Bila ada, ganti dengan foto selebar minimal 1.600 px."
            : null;
    }

    private function dimensions(string $attribute, UploadedFile $file, Closure $fail): void
    {
        $size = @getimagesize($file->getRealPath());

        if (! $size) {
            $fail('Foto tidak bisa dibaca. Simpan ulang sebagai JPG lalu coba lagi.');
        } elseif (max($size[0], $size[1]) > AllowedUpload::MAX_DIMENSION) {
            $fail('Foto terlalu besar: sisi terpanjang paling besar 8.000 px. Perkecil foto lalu coba lagi.');
        } elseif ($size[0] < self::MIN_WIDTH) {
            $fail("Foto terlalu kecil: lebarnya {$size[0]} px, minimal 1.280 px. Pakai foto asli dari kamera, bukan hasil kiriman WhatsApp.");
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->route('slide') && HeroSlide::count() >= HeroSlide::MAX) {
                    $validator->errors()->add($this->prefix().'.photo', 'Sudah ada '.HeroSlide::MAX.' slide. Hapus salah satu slide lebih dulu.');
                }
            },
        ];
    }

    public function messages(): array
    {
        $p = $this->prefix();

        return [
            "{$p}.photo.required" => 'Pilih foto slide.',
            "{$p}.photo.uploaded" => 'Foto gagal diunggah. Periksa koneksi lalu coba lagi.',
            "{$p}.photo.max" => ImageUpload::MAX_MESSAGE,
            "{$p}.photo.mimetypes" => 'Foto harus JPG, PNG, WebP, atau foto iPhone (HEIC).',
            "{$p}.alt_text.required" => 'Isi teks alternatif: jelaskan isi foto untuk pembaca layar.',
        ];
    }

    public function attributes(): array
    {
        $p = $this->prefix();

        return [
            "{$p}.photo" => 'foto',
            "{$p}.alt_text" => 'teks alternatif',
            "{$p}.caption" => 'keterangan',
            "{$p}.focus" => 'posisi fokus',
            "{$p}.is_active" => 'tampilkan di beranda',
        ];
    }
}
