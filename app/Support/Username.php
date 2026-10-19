<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalisasi username (SATU tempat: simpan, form pengguna, login, simker:create-admin). Huruf kecil tanpa spasi tepi.
 * Username boleh berupa nomor HP: bila isinya hanya angka (boleh diawali "+", dengan spasi, titik, atau tanda hubung),
 * pemisahnya dibuang dan awalan "+62"/"62" diubah jadi "0". Contoh: "+62 812-3456-7890" → "081234567890".
 */
class Username
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = Str::lower(trim($value));

        if (! preg_match('/^\+?[0-9][0-9\s.\-]*$/', $value)) {
            return $value;
        }

        $digits = preg_replace('/[\s.\-+]/', '', $value);

        return preg_replace('/^62/', '0', $digits);
    }
}
