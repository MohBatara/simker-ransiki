<?php

namespace App\Http\Requests\Organizations;

use App\Models\Product;
use App\Models\Organization;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Form produk bersama. "Produk unggulan" hanya diatur admin; dari pengguna lain diabaikan.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): Response
    {
        $record = $this->route('product');

        return $record instanceof Product
            ? Gate::forUser($this->user())->inspect('update', $record)
            : Gate::forUser($this->user())->inspect('create', [Product::class, $this->targetOrganization()]);
    }

    /** Kelompok target: dari URL (Admin/OPD) atau kelompok milik akun yang login (Kelompok). */
    public function targetOrganization(): Organization
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization ? $organization : Organization::findOrFail($this->user()->organization_id);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'is_public' => $this->boolean('is_public'),
            'is_featured' => $this->user()->isAdmin() ? $this->boolean('is_featured') : null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['nullable', 'string', 'max:30'],
            'capacity' => ['nullable', 'string', 'max:255'],
            'is_public' => ['boolean'],
            'is_featured' => $this->user()->isAdmin() ? ['boolean'] : ['exclude'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama produk',
            'description' => 'deskripsi',
            'unit' => 'satuan',
            'capacity' => 'kapasitas',
        ];
    }
}
