<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SiteContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        $phone = ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'];

        return [
            'hero_title' => ['nullable', 'string', 'max:80'],
            'hero_title_accent' => ['nullable', 'string', 'max:60'],
            'home_intro' => ['nullable', 'string', 'max:300'],
            'about_title' => ['nullable', 'string', 'max:80'],
            'profile_body' => ['nullable', 'string', 'max:20000'],
            'economic_potential' => ['nullable', 'string', 'max:20000'],
            'office_address' => ['nullable', 'string', 'max:500'],
            'office_phone' => $phone,
            'office_whatsapp' => $phone,
            'office_email' => ['nullable', 'email', 'max:255'],
            'office_hours' => ['nullable', 'string', 'max:200'],
            'cover_caption' => ['nullable', 'string', 'max:200'],
            'cover_credit' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return collect(\App\Models\SiteSetting::KEYS)->map(fn (string $label) => lcfirst($label))->all();
    }
}
