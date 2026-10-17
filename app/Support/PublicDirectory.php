<?php

namespace App\Support;

use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Village;
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

    public const TYPES_CACHE_KEY = 'public.types.v3';

    public const FOOTER_CACHE_KEY = 'public.footer';

    public const VILLAGES_CACHE_KEY = 'public.villages.v2';

    /**
     * @return array{organizations: int, villages: int, products: int, featured: int, sectors: int, updated_at: ?Carbon}
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
                'villages' => (clone $organizations)->distinct()->count('organizations.village_id'),
                'products' => (clone $products)->distinct()->count('products.id'),
                'featured' => (clone $products)->where('products.is_featured', true)->distinct()->count('products.id'),
                'sectors' => self::sectors()->count(),
                'updated_at' => $updatedAt ? Carbon::parse($updatedAt)->toIso8601String() : null,
            ];
        });

        return [...$stats, 'updated_at' => $stats['updated_at'] ? Carbon::parse($stats['updated_at']) : null];
    }

    /**
     * Bidang usaha yang punya minimal satu kelompok publik, beserta jumlah kelompok uniknya dan foto kartunya:
     * foto bidang dari Admin → foto produk publik terbaru di bidang itu → null (kartu polos).
     *
     * @return Collection<int, object{id: int, name: string, organizations_count: int, photo: ?array}>
     */
    public static function sectors(): Collection
    {
        $sectors = Cache::remember(self::SECTORS_CACHE_KEY, self::CACHE_SECONDS, function () {
            $productPhotos = Product::query()->publiclyListed()
                ->whereNotNull('products.photo_path')
                ->join('organization_sector', 'organization_sector.organization_id', '=', 'products.organization_id')
                ->groupBy('organization_sector.business_sector_id')
                ->selectRaw('organization_sector.business_sector_id as sector_id, MAX(products.id) as product_id')
                ->pluck('product_id', 'sector_id');
            $productPaths = Product::query()->whereKey($productPhotos->values())->pluck('photo_path', 'id');

            return BusinessSector::query()
                ->join('organization_sector', 'organization_sector.business_sector_id', '=', 'business_sectors.id')
                ->whereIn('organization_sector.organization_id', Organization::query()->publiclyListed()->select('organizations.id'))
                ->groupBy('business_sectors.id', 'business_sectors.name', 'business_sectors.photo_path')
                ->orderBy('business_sectors.name')
                ->get(['business_sectors.id', 'business_sectors.name', 'business_sectors.photo_path', DB::raw('COUNT(DISTINCT organization_sector.organization_id) as organizations_count')])
                ->map(fn ($sector) => [
                    'id' => $sector->id,
                    'name' => $sector->name,
                    'organizations_count' => (int) $sector->organizations_count,
                    'photo' => match (true) {
                        filled($sector->photo_path) => ['kind' => 'sector', 'id' => $sector->id, 'path' => $sector->photo_path],
                        isset($productPhotos[$sector->id]) => ['kind' => 'product', 'id' => (int) $productPhotos[$sector->id], 'path' => $productPaths[$productPhotos[$sector->id]]],
                        default => null,
                    },
                ])
                ->all();
        });

        return collect($sectors)->map(fn (array $sector) => (object) $sector);
    }

    /**
     * Jenis kelembagaan yang punya minimal satu kelompok publik, beserta jumlah kelompoknya.
     *
     * @return Collection<int, object{id: int, name: string, description: ?string, photo_path: ?string, organizations_count: int}>
     */
    public static function institutionTypes(): Collection
    {
        $types = Cache::remember(self::TYPES_CACHE_KEY, self::CACHE_SECONDS, fn () => InstitutionType::query()
            ->join('organizations', 'organizations.institution_type_id', '=', 'institution_types.id')
            ->whereIn('organizations.id', Organization::query()->publiclyListed()->select('organizations.id'))
            ->groupBy('institution_types.id', 'institution_types.name', 'institution_types.description', 'institution_types.photo_path')
            ->orderBy('institution_types.name')
            ->get(['institution_types.id', 'institution_types.name', 'institution_types.description', 'institution_types.photo_path', DB::raw('COUNT(DISTINCT organizations.id) as organizations_count')])
            ->map(fn ($type) => ['id' => $type->id, 'name' => $type->name, 'description' => $type->description, 'photo_path' => $type->photo_path, 'organizations_count' => (int) $type->organizations_count])
            ->all());

        return collect($types)->map(fn (array $type) => (object) $type);
    }

    /**
     * Semua kampung (Data Master), urut jumlah kelompok publik terbanyak lalu abjad (0 = belum ada kelompok terdaftar),
     * beserta maks 3 bidang usaha kelompok publik di kampung itu (terbanyak dipakai, lalu abjad).
     *
     * @return Collection<int, object{id: int, name: string, organizations_count: int, sectors: list<string>}>
     */
    public static function villages(): Collection
    {
        $villages = Cache::remember(self::VILLAGES_CACHE_KEY, self::CACHE_SECONDS, function () {
            $sectors = DB::table('organization_sector')
                ->join('organizations', 'organizations.id', '=', 'organization_sector.organization_id')
                ->join('business_sectors', 'business_sectors.id', '=', 'organization_sector.business_sector_id')
                ->whereIn('organizations.id', Organization::query()->publiclyListed()->select('organizations.id'))
                ->groupBy('organizations.village_id', 'business_sectors.id', 'business_sectors.name')
                ->get(['organizations.village_id', 'business_sectors.name', DB::raw('COUNT(DISTINCT organizations.id) as uses')])
                ->sortBy([['uses', 'desc'], ['name', 'asc']])
                ->groupBy('village_id')
                ->map(fn (Collection $rows) => $rows->take(3)->pluck('name')->all());

            return Village::query()
                ->withCount(['organizations' => fn ($query) => $query->publiclyListed()])
                ->get(['id', 'name'])
                ->sortBy([['organizations_count', 'desc'], ['name', 'asc']])
                ->map(fn (Village $village) => [
                    'id' => $village->id,
                    'name' => $village->name,
                    'organizations_count' => (int) $village->organizations_count,
                    'sectors' => $sectors[$village->id] ?? [],
                ])
                ->values()
                ->all();
        });

        return collect($villages)->map(fn (array $village) => (object) $village);
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
