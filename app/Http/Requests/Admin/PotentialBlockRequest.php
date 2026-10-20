<?php

namespace App\Http\Requests\Admin;

/**
 * Satu blok "Potensi ekonomi" (Profil Ransiki). Isian berawalan "potensi_{n}[...]"; aturan foto sama dengan kartu
 * Mengenal Distrik Ransiki (JPG/PNG/WebP atau HEIC, maks 10 MB, lebar minimal 800 px).
 */
class PotentialBlockRequest extends AboutCardRequest
{
    public static function prefixFor(int $n): string
    {
        return "potensi_{$n}";
    }

    public function prefix(): string
    {
        return self::prefixFor((int) $this->route('n'));
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            "{$this->prefix()}.text" => ['nullable', 'string', 'max:400'],
        ];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), "{$this->prefix()}.text" => 'teks'];
    }
}
