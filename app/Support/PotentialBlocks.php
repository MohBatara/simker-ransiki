<?php

namespace App\Support;

use App\Models\BusinessSector;
use App\Models\SiteSetting;

/**
 * Tiga blok "Potensi ekonomi" di Profil Ransiki. Isian Admin (Konten Website > Profil Ransiki), atau nilai bawaan.
 * Jumlah kelompok hanya kelompok publik (PublicDirectory::sectors()). Foto: foto blok, lalu foto bidang usaha
 * (foto bidang dari Admin atau foto produk publik), lalu foto bawaan.
 */
class PotentialBlocks
{
    public const COUNT = 3;

    /**
     * @param  array<string, ?string>  $settings  SiteSetting::values()
     * @return list<array{n: int, title: string, text: string, sector: ?string, count: int, url: ?string, photo: array}>
     */
    public static function all(array $settings): array
    {
        $chosen = collect(range(1, self::COUNT))->mapWithKeys(fn (int $n) => [$n => $settings["potential_{$n}_sector"] ?? null]);
        $sectors = BusinessSector::query()
            ->whereIn('id', $chosen->filter())
            ->orWhereIn('name', array_values(SiteSetting::ABOUT_CARD_SECTORS))
            ->get(['id', 'name']);
        $public = PublicDirectory::sectors()->keyBy('id');

        return collect(range(1, self::COUNT))->map(function (int $n) use ($settings, $chosen, $sectors, $public) {
            $sector = $chosen[$n]
                ? $sectors->firstWhere('id', (int) $chosen[$n])
                : $sectors->first(fn (BusinessSector $sector) => strcasecmp($sector->name, SiteSetting::ABOUT_CARD_SECTORS[$n]) === 0);
            $listed = $sector ? $public->get($sector->id) : null;

            return [
                'n' => $n,
                'title' => $settings["potential_{$n}_title"] ?? SiteSetting::DEFAULTS["potential_{$n}_title"],
                'text' => $settings["potential_{$n}_text"] ?? SiteSetting::DEFAULTS["potential_{$n}_text"],
                'sector' => $sector?->name,
                'count' => $listed->organizations_count ?? 0,
                'url' => $listed ? route('public.directory', ['bidang' => $sector->id]) : null,
                'photo' => self::photo($n, $settings) ?? ($listed ? PublicImages::sector($listed) : null) ?? PublicImages::heroDefault(),
            ];
        })->all();
    }

    /** Foto blok dari Admin (route /foto-potensi/{n}), atau null. */
    public static function photo(int $n, array $settings): ?array
    {
        $path = $settings["potential_{$n}_photo_path"] ?? null;

        return $path ? PublicImages::potential($n, $path) : null;
    }
}
