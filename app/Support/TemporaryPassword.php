<?php

namespace App\Support;

/**
 * Kata sandi sementara yang mudah diketik di HP: huruf kecil dan angka tanpa karakter yang
 * mirip (l/1, o/0), dua kelompok lima karakter, mis. "hk7mp-x3qa9".
 */
class TemporaryPassword
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public static function generate(): string
    {
        $characters = '';
        for ($i = 0; $i < 10; $i++) {
            $characters .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return substr($characters, 0, 5).'-'.substr($characters, 5);
    }
}
