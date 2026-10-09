<?php

namespace App\Http\Requests\Organizations;

use App\Enums\LicenseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LicenseRequest extends FormRequest
{
    /** Saran isian jenis izin; jenis lain tetap boleh diketik. */
    public const SUGGESTED_TYPES = ['NIB', 'PIRT', 'Sertifikat Halal', 'BPOM'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:100'],
            'number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(LicenseStatus::class)],
            'submitted_on' => ['nullable', 'date', 'before_or_equal:today'],
            'issued_on' => ['nullable', 'date', 'after_or_equal:submitted_on', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'issued_on.after_or_equal' => 'Tanggal terbit tidak boleh sebelum tanggal diajukan.',
            'submitted_on.before_or_equal' => 'Tanggal diajukan tidak boleh di masa depan.',
            'issued_on.before_or_equal' => 'Tanggal terbit tidak boleh di masa depan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'jenis izin',
            'number' => 'nomor izin',
            'status' => 'status izin',
            'submitted_on' => 'tanggal diajukan',
            'issued_on' => 'tanggal terbit',
            'notes' => 'catatan',
        ];
    }
}
