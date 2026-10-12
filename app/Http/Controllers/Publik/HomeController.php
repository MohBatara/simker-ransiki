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
use App\Support\Initials;
use App\Support\PublicDirectory;
use App\Support\PublicImages;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const FEATURED_LIMIT = 4;

    private const SLIDE_LIMIT = 3;

    private const POST_LIMIT = 5;

    private const AVATAR_LIMIT = 3;

    public function __invoke(): View
    {
        return view('publik.home', [
            'stats' => PublicDirectory::stats(),
            'sectors' => PublicDirectory::sectors(),
            'types' => $this->typeCards(PublicDirectory::institutionTypes()),
            'settings' => SiteSetting::values(),
            'slides' => $this->slides(),
            'about' => PublicImages::slot('tentang') ?? PublicImages::slot('sampul'),
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
     * Kartu "Jenis kelembagaan" (bento): jumlah kelompok publik terkini, maksimal 3 avatar kelompok publik (foto produk
     * publik pertama, atau inisial), urut dari jenis terbanyak. Foto kartu: foto jenis dari Data Master; kartu pertama
     * (utama) tanpa foto jenis memakai foto bidang usaha yang paling banyak dipakai kelompok jenis itu (Konten Website).
     *
     * @return Collection<int, object>
     */
    private function typeCards(Collection $types): Collection
    {
        if ($types->isEmpty()) {
            return $types;
        }

        $organizations = Organization::query()->publiclyListed()
            ->whereIn('organizations.institution_type_id', $types->pluck('id'))
            ->orderBy('organizations.name')
            ->get(['organizations.id', 'organizations.name', 'organizations.institution_type_id'])
            ->groupBy('institution_type_id');

        $shown = $organizations->flatMap(fn (Collection $group) => $group->take(self::AVATAR_LIMIT));
        $photos = Product::query()->publiclyListed()
            ->whereIn('products.organization_id', $shown->pluck('id'))
            ->whereNotNull('products.photo_path')
            ->orderBy('products.id')
            ->get(['products.id', 'products.organization_id', 'products.photo_path'])
            ->unique('organization_id')
            ->keyBy('organization_id');

        $cards = $types
            ->map(function (object $type) use ($organizations, $photos) {
                $group = $organizations->get($type->id, collect());

                return (object) [
                    ...(array) $type,
                    'organizations_count' => $group->count(),
                    'avatars' => $group->take(self::AVATAR_LIMIT)->map(function (Organization $organization) use ($photos) {
                        $sources = $photos->get($organization->id)?->photoSources();

                        return [
                            'name' => $organization->name,
                            'initials' => Initials::organization($organization->name),
                            'photo' => $sources,
                        ];
                    })->values()->all(),
                    'more' => max(0, $group->count() - self::AVATAR_LIMIT),
                    // Kartu kecil: foto jenis dari Data Master (opsional). Kartu utama diisi di bawah.
                    'photo' => PublicImages::institutionTypePhoto($type),
                ];
            })
            ->filter(fn (object $card) => $card->organizations_count > 0)
            ->sortBy([['organizations_count', 'desc'], ['name', 'asc']])
            ->values();

        $featured = $cards->first();

        if ($featured && ! $featured->photo) {
            $sector = BusinessSector::query()
                ->whereNotNull('business_sectors.photo_path')
                ->join('organization_sector', 'organization_sector.business_sector_id', '=', 'business_sectors.id')
                ->whereIn('organization_sector.organization_id', $organizations->get($featured->id)->pluck('id'))
                ->groupBy('business_sectors.id', 'business_sectors.name', 'business_sectors.photo_path')
                ->orderByRaw('COUNT(*) DESC')
                ->orderBy('business_sectors.name')
                ->first(['business_sectors.id', 'business_sectors.name', 'business_sectors.photo_path']);
            $featured->photo = $sector ? PublicImages::sectorPhoto($sector) : null;
        }

        return $cards;
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
