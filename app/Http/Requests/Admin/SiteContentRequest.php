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
            'how_title' => ['nullable', 'string', 'max:80'],
            ...collect(range(1, 4))->flatMap(fn (int $i) => [
                "how_{$i}_title" => ['nullable', 'string', 'max:60'],
                "how_{$i}_text" => ['nullable', 'string', 'max:160'],
            ])->all(),
            'profile_lead' => ['nullable', 'string', 'max:160'],
            'profile_intro' => ['nullable', 'string', 'max:600'],
            'profile_body' => ['nullable', 'string', 'max:20000'],
            ...collect(\App\Models\SiteSetting::PROFILE_FACT_KEYS)->map(fn () => ['nullable', 'string', 'max:80'])->all(),
            'profile_data_source' => ['nullable', 'string', 'max:200'],
            'potential_intro' => ['nullable', 'string', 'max:200'],
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
