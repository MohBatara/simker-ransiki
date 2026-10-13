<?php

namespace App\Http\Controllers\Publik;

use App\Enums\HeroFocus;
use App\Http\Controllers\Controller;
use App\Models\BusinessSector;
use App\Models\HeroSlide;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\AboutCards;
use App\Support\PublicDirectory;
use App\Support\PublicImages;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const FEATURED_LIMIT = 4;

    private const SLIDE_LIMIT = 3;

    private const POST_LIMIT = 5;


    public function __invoke(): View
    {
        return view('publik.home', [
            'stats' => PublicDirectory::stats(),
            'sectors' => PublicDirectory::sectors(),
            'types' => $this->typeCards(PublicDirectory::institutionTypes()),
            'settings' => $settings = SiteSetting::values(),
            'aboutCards' => AboutCards::all($settings),
            'slides' => $this->slides(),
            'posts' => Post::query()->publiclyListed()->latest('published_at')->latest('id')->limit(self::POST_LIMIT)->get(PostController::COLUMNS),
            'featured' => $this->featured(),
        ]);
    }

    /**
     * Slide hero yang diatur Admin (maks 3, urut posisi). Belum ada yang aktif: satu foto bawaan dari repo,
     * tidak pernah foto produk kelompok.
     *
     * @return list<array{image: array, alt: string, caption: ?string, focus: string}>
     */
    private function slides(): array
    {
        $slides = HeroSlide::query()->shown()->get()->map(fn (HeroSlide $slide) => [
            'image' => PublicImages::hero($slide),
            'alt' => $slide->alt_text,
            'caption' => $slide->caption,
            'focus' => $slide->focus->objectPosition(),
        ]);

        return $slides->isNotEmpty() ? $slides->all() : [[
            'image' => PublicImages::heroDefault(),
            'alt' => 'Pemandangan pesisir berbukit dengan pohon kakao berbuah di tepi kanan.',
            'caption' => null,
            'focus' => HeroFocus::Center->objectPosition(),
        ]];
    }

    /**
     * Daftar "Jenis kelembagaan": jumlah kelompok publik terkini, urut terbanyak. Foto tiap jenis: foto jenis dari Data
     * Master, lalu foto bidang usaha yang paling banyak dipakai kelompok publik jenis itu (Konten Website), lalu foto
     * bawaan Beranda. Tidak pernah foto produk kelompok.
     *
     * @return Collection<int, object>
     */
    private function typeCards(Collection $types): Collection
    {
        if ($types->isEmpty()) {
            return $types;
        }

        $counts = Organization::query()->publiclyListed()
            ->whereIn('organizations.institution_type_id', $types->pluck('id'))
            ->groupBy('organizations.institution_type_id')
            ->selectRaw('organizations.institution_type_id as type_id, COUNT(DISTINCT organizations.id) as total')
            ->pluck('total', 'type_id');

        // Bidang berfoto terbanyak per jenis: satu query, dipilih di PHP (jumlah terbanyak, lalu nama).
        $sectors = BusinessSector::query()
            ->whereNotNull('business_sectors.photo_path')
            ->join('organization_sector', 'organization_sector.business_sector_id', '=', 'business_sectors.id')
            ->join('organizations', 'organizations.id', '=', 'organization_sector.organization_id')
            ->whereIn('organizations.id', Organization::query()->publiclyListed()->select('organizations.id'))
            ->whereIn('organizations.institution_type_id', $types->pluck('id'))
            ->groupBy('organizations.institution_type_id', 'business_sectors.id', 'business_sectors.name', 'business_sectors.photo_path')
            ->get(['organizations.institution_type_id', 'business_sectors.id', 'business_sectors.name', 'business_sectors.photo_path', DB::raw('COUNT(*) as uses')])
            ->sortBy([['uses', 'desc'], ['name', 'asc']])
            ->unique('institution_type_id')
            ->keyBy('institution_type_id');

        $fallback = PublicImages::heroDefault();

        return $types
            ->map(fn (object $type) => (object) [
                ...(array) $type,
                'organizations_count' => (int) ($counts[$type->id] ?? 0),
                'photo' => PublicImages::institutionTypePhoto($type)
                    ?? (isset($sectors[$type->id]) ? PublicImages::sectorPhoto($sectors[$type->id]) : null)
                    ?? $fallback,
            ])
            ->filter(fn (object $type) => $type->organizations_count > 0)
            ->sortBy([['organizations_count', 'desc'], ['name', 'asc']])
            ->values();
    }

    /**
     * Produk unggulan (maks 4): yang berfoto lebih dulu, sisanya diisi produk tanpa foto.
     * Produk bernama sama tidak ditaruh berdampingan.
     */
    private function featured(): Collection
    {
        $query = fn () => Product::query()->publiclyListed()
            ->where('products.is_featured', true)
            ->with(['organization:id,slug,name,village_id', 'organization.village:id,name', 'organization.sectors:id,name,photo_path'])
            ->orderBy('products.name')
            ->limit(12);

        $withPhoto = $query()->whereNotNull('products.photo_path')->get(ProductController::COLUMNS);
        $withoutPhoto = $withPhoto->count() < self::FEATURED_LIMIT
            ? $query()->whereNull('products.photo_path')->get(ProductController::COLUMNS)
            : collect();

        return self::spreadSameNames($withPhoto)->concat(self::spreadSameNames($withoutPhoto))->take(self::FEATURED_LIMIT)->values();
    }

    /** Susun ulang agar dua produk dengan nama sama tidak berdampingan bila masih ada pilihan lain. */
    private static function spreadSameNames(Collection $products): Collection
    {
        $remaining = $products->values();
        $ordered = collect();

        while ($remaining->isNotEmpty()) {
            $lastName = mb_strtolower((string) $ordered->last()?->name);
            $index = $remaining->search(fn (Product $product) => mb_strtolower($product->name) !== $lastName);
            $index = $index === false ? 0 : $index;
            $ordered->push($remaining->pull($index));
            $remaining = $remaining->values();
        }

        return $ordered;
    }
}
