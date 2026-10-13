<?php

namespace App\Support;

use App\Models\BusinessSector;
use App\Models\SiteSetting;

/**
 * Tiga kartu foto bagian "Mengenal Distrik Ransiki" di beranda. Isian Admin (Konten Website > Beranda), atau nilai
 * bawaan. Foto: foto kartu, lalu foto bidang usaha tujuan (Konten Website), lalu foto bawaan Beranda.
 */
class AboutCards
{
    public const COUNT = 3;

    /**
     * @param  array<string, ?string>  $settings  SiteSetting::values()
     * @return list<array{n: int, title: string, text: string, url: string, photo: array}>
     */
    public static function all(array $settings): array
    {
        $chosen = collect(range(1, self::COUNT))->mapWithKeys(fn (int $n) => [$n => $settings["about_card_{$n}_sector"] ?? null]);
        $sectors = BusinessSector::query()
            ->whereIn('id', $chosen->filter())
            ->orWhereIn('name', array_values(SiteSetting::ABOUT_CARD_SECTORS))
            ->get(['id', 'name', 'photo_path']);

        return collect(range(1, self::COUNT))->map(function (int $n) use ($settings, $chosen, $sectors) {
            $sector = $chosen[$n]
                ? $sectors->firstWhere('id', (int) $chosen[$n])
                : $sectors->first(fn (BusinessSector $sector) => strcasecmp($sector->name, SiteSetting::ABOUT_CARD_SECTORS[$n]) === 0);

            return [
                'n' => $n,
                'title' => $settings["about_card_{$n}_title"] ?? SiteSetting::DEFAULTS["about_card_{$n}_title"],
                'text' => $settings["about_card_{$n}_text"] ?? SiteSetting::DEFAULTS["about_card_{$n}_text"],
                'url' => route('public.directory', $sector ? ['bidang' => $sector->id] : []),
                'photo' => self::photo($n, $settings) ?? ($sector ? PublicImages::sectorPhoto($sector) : null) ?? PublicImages::heroDefault(),
            ];
        })->all();
    }

    /** Foto kartu dari Admin (route /foto-kartu/{n}), atau null. */
    public static function photo(int $n, array $settings): ?array
    {
        $path = $settings["about_card_{$n}_photo_path"] ?? null;

        return $path ? PublicImages::aboutCard($n, $path) : null;
    }
}
