<?php

namespace App\Support;

/**
 * Ikon garis (Heroicons outline) untuk bidang usaha dan jenis kelembagaan di website publik.
 * Dicocokkan dari kata di nama data master, sehingga nama baru tetap mendapat ikon bawaan.
 */
class PublicIcons
{
    /** Kata kunci (huruf kecil) => ikon; yang pertama cocok dipakai. */
    private const SECTORS = [
        'pertanian' => 'sun',
        'tani' => 'sun',
        'perkebunan' => 'map',
        'kebun' => 'map',
        'perikanan' => 'lifebuoy',
        'nelayan' => 'lifebuoy',
        'pengolahan' => 'cog-6-tooth',
        'perdagangan' => 'shopping-cart',
        'umkm' => 'building-storefront',
    ];

    private const INSTITUTION_TYPES = [
        'bumdes' => 'building-office-2',
        'kub' => 'user-group',
        'kdmp' => 'building-library',
        'knmp' => 'building-library',
        'koperasi' => 'building-library',
        ...self::SECTORS,
    ];

    public static function sector(?string $name): string
    {
        return self::match(self::SECTORS, $name, 'squares-2x2');
    }

    public static function institutionType(?string $name): string
    {
        return self::match(self::INSTITUTION_TYPES, $name, 'rectangle-group');
    }

    private static function match(array $map, ?string $name, string $default): string
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower((string) $name), flags: PREG_SPLIT_NO_EMPTY);

        foreach ($map as $keyword => $icon) {
            if (in_array($keyword, $words, true)) {
                return $icon;
            }
        }

        return $default;
    }
}
