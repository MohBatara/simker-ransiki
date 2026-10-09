<?php

namespace App\Http\Requests\Files;

use App\Models\License;
use App\Models\Organization;
use App\Models\Product;
use App\Rules\AllowedUpload;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Satu file per permintaan (dikirim lewat XHR dengan bilah progres).
 */
class UploadRequest extends FormRequest
{
    public const MAX_KILOBYTES = 10240;

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->target());
    }

    /**
     * Induk file: produk (foto) atau kelompok/izin (lampiran). Hak unggah = hak mengubah induknya.
     */
    public function target(): Model
    {
        if ($this->route('product') instanceof Product) {
            return $this->route('product');
        }

        return match ($this->route('type')) {
            'kelompok' => Organization::findOrFail($this->route('id')),
            'izin' => License::findOrFail($this->route('id')),
        };
    }

    /** Foto produk hanya menerima gambar; lampiran juga menerima PDF. */
    protected function kind(): string
    {
        return $this->routeIs('product-photos.*') ? 'photo' : 'document';
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.self::MAX_KILOBYTES, new AllowedUpload($this->kind())],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file yang akan diunggah.',
            'file.uploaded' => 'File gagal diunggah. Periksa koneksi lalu coba lagi.',
            'file.max' => 'Ukuran file paling besar 10 MB. Perkecil file lalu coba lagi.',
        ];
    }
}
