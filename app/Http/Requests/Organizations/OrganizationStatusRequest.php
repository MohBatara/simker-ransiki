<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrganizationStatus::class), Rule::notIn([$this->route('organization')->status->value])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.not_in' => 'Kelompok sudah berstatus '.lcfirst($this->route('organization')->status->label()).'. Pilih status lain.',
            'reason.required' => 'Tulis alasan singkat perubahan status.',
            'reason.min' => 'Alasan minimal 5 karakter.',
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'status kelompok', 'reason' => 'alasan'];
    }

    public function status(): OrganizationStatus
    {
        return OrganizationStatus::from($this->validated('status'));
    }
}
