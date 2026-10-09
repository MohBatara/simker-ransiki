<?php

namespace App\Http\Requests\Organizations;

use App\Enums\LegalStatus;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Form profil kelompok bersama untuk Admin, OPD, dan Kelompok.
 * Otorisasi lewat Policy di route; kolom khusus admin diabaikan (exclude) untuk pengguna lain.
 */
class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function canAdminister(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            ? $this->user()->can('administer', $organization)
            : $this->user()->can('create', Organization::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'show_contact_publicly' => $this->boolean('show_contact_publicly'),
            // Checkbox kosong tidak terkirim; tanpa ini semua OPD tidak bisa dilepas.
            'opds' => $this->canAdminister() ? $this->input('opds', []) : null,
            'is_public' => $this->canAdminister() ? $this->boolean('is_public') : null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        $adminOnly = fn (array $rules) => $this->canAdminister() ? $rules : ['exclude'];
        $text = fn (int $max) => ['nullable', 'string', 'max:'.$max];

        return [
            'name' => $adminOnly(['required', 'string', 'max:255']),
            'institution_type_id' => $adminOnly(['required', 'integer', Rule::exists('institution_types', 'id')]),
            'village_id' => $adminOnly(['required', 'integer', Rule::exists('villages', 'id')]),
            'opds' => $adminOnly(['array']),
            'opds.*' => $adminOnly(['integer', Rule::exists('opds', 'id')]),
            'status' => $adminOnly(['required', Rule::enum(OrganizationStatus::class)]),
            'is_public' => $adminOnly(['boolean']),
            'reporting_month' => $adminOnly(['required', 'integer', 'between:1,12']),
            'reporting_year' => $adminOnly(['required', 'integer', 'between:2020,'.(now()->year + 1)]),

            'address' => $text(500),
            'founded_year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'leader_name' => $text(255),
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'members_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'description' => $text(2000),
            'sectors' => ['required', 'array', 'min:1'],
            'sectors.*' => ['integer', Rule::exists('business_sectors', 'id')],
            'production_capacity' => $text(2000),
            'markets' => $text(2000),
            'workforce_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'assets' => $text(2000),
            'facilities' => $text(2000),
            'equipment' => $text(2000),
            'legal_status' => ['required', Rule::enum(LegalStatus::class)],
            'legal_number' => $text(255),
            'legal_notes' => $text(2000),
            'show_contact_publicly' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sectors.required' => 'Pilih minimal satu bidang usaha.',
            'sectors.min' => 'Pilih minimal satu bidang usaha.',
            'reporting_year.between' => 'Tahun mulai wajib lapor harus antara 2020 dan :max.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama kelompok',
            'institution_type_id' => 'jenis kelembagaan',
            'village_id' => 'kampung',
            'status' => 'status kelompok',
            'reporting_month' => 'bulan mulai wajib lapor',
            'reporting_year' => 'tahun mulai wajib lapor',
            'address' => 'alamat',
            'founded_year' => 'tahun berdiri',
            'leader_name' => 'nama ketua',
            'members_count' => 'jumlah anggota',
            'description' => 'deskripsi',
            'production_capacity' => 'kapasitas produksi',
            'markets' => 'pasar',
            'workforce_count' => 'jumlah tenaga kerja',
            'assets' => 'aset',
            'facilities' => 'fasilitas',
            'equipment' => 'peralatan',
            'legal_status' => 'status hukum',
            'legal_number' => 'nomor legalitas',
            'legal_notes' => 'catatan legalitas',
        ];
    }

    /**
     * Data siap simpan untuk SaveOrganization: bulan + tahun wajib lapor digabung menjadi tanggal 1.
     */
    public function organizationData(): array
    {
        $data = Arr::except($this->validated(), ['reporting_month', 'reporting_year']);

        if ($this->canAdminister()) {
            $data['reporting_starts_on'] = Carbon::create((int) $this->validated('reporting_year'), (int) $this->validated('reporting_month'), 1)->toDateString();
        }

        return $data;
    }
}
