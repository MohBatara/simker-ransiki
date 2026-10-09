<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form produk bersama. "Produk unggulan" hanya diatur admin; dari pengguna lain diabaikan.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
