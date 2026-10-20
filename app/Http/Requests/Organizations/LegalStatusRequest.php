<?php

namespace App\Http\Requests\Organizations;

use App\Enums\LegalStatus;
use App\Models\Organization;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Form kecil status legalitas di halaman Legalitas dan izin milik kelompok.
 */
class LegalStatusRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->targetOrganization());
    }

    public function targetOrganization(): Organization
    {
        return Organization::findOrFail($this->user()->organization_id);
    }

    public function rules(): array
    {
        return [
            'legal_status' => ['required', Rule::enum(LegalStatus::class)],
            'legal_number' => ['nullable', 'string', 'max:255'],
            'legal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'legal_status' => 'status hukum',
            'legal_number' => 'nomor legalitas',
            'legal_notes' => 'catatan legalitas',
        ];
    }
}
