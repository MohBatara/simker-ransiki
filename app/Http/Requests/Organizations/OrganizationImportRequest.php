<?php

namespace App\Http\Requests\Organizations;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Unggahan file impor kelompok (khusus Admin): .xlsx atau .csv, maksimal 2 MB. Tipe dicek dari isi file
 * (finfo), bukan hanya ekstensinya.
 */
class OrganizationImportRequest extends FormRequest
{
    /** Tipe isi yang diterima per ekstensi. */
    private const MIMES = [
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'text/x-csv'],
    ];

    public function authorize(): bool
    {
        return $this->user()->can('create', Organization::class);
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:2048']];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file .xlsx atau .csv.',
            'file.file' => 'File gagal diunggah. Coba lagi.',
            'file.uploaded' => 'File gagal diunggah. Coba lagi.',
            'file.max' => 'Ukuran file paling besar 2 MB. Bagi menjadi beberapa file.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('file')) {
                return;
            }

            $type = $this->fileType();
            if ($type === null) {
                $validator->errors()->add('file', 'Gunakan file .xlsx atau .csv. Di Excel: File > Simpan sebagai > Buku Kerja Excel (.xlsx).');
            }
        }];
    }

    /** 'xlsx' atau 'csv' bila ekstensi dan isi file cocok; null bila tidak. */
    public function fileType(): ?string
    {
        $file = $this->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        return in_array($mime, self::MIMES[$extension] ?? [], true) ? $extension : null;
    }
}
