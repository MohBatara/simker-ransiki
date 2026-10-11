<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Kata sandi baru dari tautan undangan atau tautan atur ulang (tanpa kata sandi lama). */
class NewPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
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
