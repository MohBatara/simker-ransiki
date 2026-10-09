<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(8), 'different:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok.',
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
            'password.confirmed' => 'Ulangi kata sandi tidak sama dengan kata sandi baru.',
        ];
    }

    public function attributes(): array
    {
        return [
            'password' => 'kata sandi baru',
        ];
    }
}
