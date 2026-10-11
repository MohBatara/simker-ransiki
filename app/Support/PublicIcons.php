<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Ikon website publik, dalam bentuk "lucide:nama" (resources/svg/lucide) atau "heroicon:nama" (Heroicons outline),
 * dirender komponen x-public.icon. Bidang usaha memakai Lucide menurut config simker.public_icons.
 */
class PublicIcons
{
    /** Jenis kelembagaan berbentuk badan/kelompok usaha (kata di nama => Heroicons). */
    private const INSTITUTION_TYPES = [
        'bumdes' => 'building-office-2',
        'kub' => 'user-group',
        'kdmp' => 'building-library',
        'knmp' => 'building-library',
        'koperasi' => 'building-library',
    ];

    /** @var array<string, string> */
    private static array $svgs = [];

    public static function sector(?string $name): string
    {
        $icons = config('simker.public_icons');

        return 'lucide:'.($icons['sectors'][Str::slug((string) $name)] ?? $icons['default']);
    }

    /** "Kelompok Perikanan" memakai ikon bidang usaha yang namanya muncul di jenis itu (ikan). */
    public static function institutionType(?string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower((string) $name), flags: PREG_SPLIT_NO_EMPTY);
        $sectors = config('simker.public_icons.sectors');

        foreach ($words as $word) {
            if (isset(self::INSTITUTION_TYPES[$word])) {
                return 'heroicon:'.self::INSTITUTION_TYPES[$word];
            }
        }

        foreach ($words as $word) {
            if (isset($sectors[$word])) {
                return 'lucide:'.$sectors[$word];
            }
        }

        return 'heroicon:rectangle-group';
    }

    /** Isi <svg> Lucide dari repo (hanya elemen path/circle, aman ditulis mentah). */
    public static function lucide(string $name): string
    {
        $name = basename($name);

        return self::$svgs[$name] ??= file_get_contents(resource_path("svg/lucide/{$name}.svg"));
    }
}
