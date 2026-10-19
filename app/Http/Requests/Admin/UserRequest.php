<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Admin tidak bisa mengubah peran akunnya sendiri agar tidak terkunci dari area admin. */
    public function editingSelf(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && $user->is($this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => \App\Support\Username::normalize((string) $this->input('username')),
            'email' => $this->filled('email') ? Str::lower(trim((string) $this->input('email'))) : null,
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['nullable', 'required_if:access,invite', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            // Hanya saat menambah akun: undangan email (buat kata sandi sendiri) atau kata sandi sementara.
            'access' => $user ? ['exclude'] : ['nullable', Rule::in(['invite', 'temporary'])],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'role' => $this->editingSelf() ? ['exclude'] : ['required', Rule::enum(Role::class)],
            'opd_id' => ['exclude_unless:role,opd', 'required', 'integer', Rule::exists('opds', 'id')],
            'organization_id' => ['exclude_unless:role,kelompok', 'required', 'integer', Rule::exists('organizations', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
            'opd_id.required' => 'Pilih OPD untuk akun OPD.',
            'organization_id.required' => 'Pilih kelompok untuk akun kelompok.',
            'email.required_if' => 'Isi email untuk mengirim undangan, atau pilih kata sandi sementara.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'role' => 'peran',
            'opd_id' => 'OPD',
            'organization_id' => 'kelompok',
        ];
    }

    public function wantsInvitation(): bool
    {
        return $this->validated('access') === 'invite' && filled($this->validated('email'));
    }

    /**
     * Data siap simpan: OPD dan kelompok hanya terisi sesuai perannya, selebihnya dikosongkan.
     */
    public function userData(): array
    {
        $data = $this->validated();
        $role = Role::from($data['role'] ?? $this->route('user')->role->value);

        return [
            ...Arr::except($data, ['opd_id', 'organization_id', 'access']),
            'opd_id' => $role === Role::Opd ? $data['opd_id'] : null,
            'organization_id' => $role === Role::Kelompok ? $data['organization_id'] : null,
        ];
    }
}
