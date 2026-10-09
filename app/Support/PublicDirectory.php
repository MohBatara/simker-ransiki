<?php

namespace App\Support;

use App\Models\BusinessSector;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Data ringkas untuk website publik. Semua angka hanya menghitung kelompok dan produk yang terdaftar
 * publik, dihitung unik, dan di-cache singkat.
 *
 * Cache hanya menyimpan array dan teks: Laravel menolak membuka objek dari cache
 * (config cache.serializable_classes = false), jadi objek/Carbon dibentuk ulang setelah dibaca.
 */
class PublicDirectory
{
    public const CACHE_SECONDS = 600;

    public const STATS_CACHE_KEY = 'public.stats';

    public const SECTORS_CACHE_KEY = 'public.sectors';

    public const FOOTER_CACHE_KEY = 'public.footer';

    /**
     * @return array{organizations: int, products: int, sectors: int, updated_at: ?Carbon}
     */
    public static function stats(): array
    {
        $stats = Cache::remember(self::STATS_CACHE_KEY, self::CACHE_SECONDS, function () {
            $organizations = Organization::query()->publiclyListed();
            $products = Product::query()->publiclyListed();

            $updatedAt = collect([
                (clone $organizations)->max('organizations.updated_at'),
                (clone $products)->max('products.updated_at'),
            ])->filter()->max();

            return [
                'organizations' => (clone $organizations)->distinct()->count('organizations.id'),
                'products' => (clone $products)->distinct()->count('products.id'),
                'sectors' => self::sectors()->count(),
                'updated_at' => $updatedAt ? Carbon::parse($updatedAt)->toIso8601String() : null,
            ];
        });

        return [...$stats, 'updated_at' => $stats['updated_at'] ? Carbon::parse($stats['updated_at']) : null];
    }

    /**
     * Bidang usaha yang punya minimal satu kelompok publik, beserta jumlah kelompok uniknya.
     *
     * @return Collection<int, object{id: int, name: string, organizations_count: int}>
     */
    public static function sectors(): Collection
    {
        $sectors = Cache::remember(self::SECTORS_CACHE_KEY, self::CACHE_SECONDS, fn () => BusinessSector::query()
            ->join('organization_sector', 'organization_sector.business_sector_id', '=', 'business_sectors.id')
            ->whereIn('organization_sector.organization_id', Organization::query()->publiclyListed()->select('organizations.id'))
            ->groupBy('business_sectors.id', 'business_sectors.name')
            ->orderBy('business_sectors.name')
            ->get(['business_sectors.id', 'business_sectors.name', DB::raw('COUNT(DISTINCT organization_sector.organization_id) as organizations_count')])
            ->map(fn ($sector) => ['id' => $sector->id, 'name' => $sector->name, 'organizations_count' => (int) $sector->organizations_count])
            ->all());

        return collect($sectors)->map(fn (array $sector) => (object) $sector);
    }

    /** Kontak kantor distrik dan daftar OPD untuk footer. */
    public static function footer(): array
    {
        return [
            'settings' => SiteSetting::values(),
            'opds' => Cache::remember(self::FOOTER_CACHE_KEY, self::CACHE_SECONDS, fn () => Opd::orderBy('name')->pluck('name')->all()),
        ];
    }
}
