<?php

namespace App\Http\Requests\Organizations;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Organization;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LicenseRequest extends FormRequest
{
    /** Saran isian jenis izin; jenis lain tetap boleh diketik. */
    public const SUGGESTED_TYPES = ['NIB', 'PIRT', 'Sertifikat Halal', 'BPOM'];

    public function authorize(): Response
    {
        $record = $this->route('license');

        return $record instanceof License
            ? Gate::forUser($this->user())->inspect('update', $record)
            : Gate::forUser($this->user())->inspect('create', [License::class, $this->targetOrganization()]);
    }

    /** Kelompok target: dari URL (Admin/OPD) atau kelompok milik akun yang login (Kelompok). */
    public function targetOrganization(): Organization
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization ? $organization : Organization::findOrFail($this->user()->organization_id);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:100'],
            'number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(LicenseStatus::class)],
            'submitted_on' => ['nullable', 'date', 'before_or_equal:today'],
            'issued_on' => ['nullable', 'date', 'after_or_equal:submitted_on', 'before_or_equal:today'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'issued_on.after_or_equal' => 'Tanggal terbit tidak boleh sebelum tanggal diajukan.',
            'submitted_on.before_or_equal' => 'Tanggal diajukan tidak boleh di masa depan.',
            'issued_on.before_or_equal' => 'Tanggal terbit tidak boleh di masa depan.',
            'expires_on.after_or_equal' => 'Tanggal berlaku sampai tidak boleh sebelum tanggal terbit.',
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
            'expires_on' => 'tanggal berlaku sampai',
            'notes' => 'catatan',
        ];
    }
}
