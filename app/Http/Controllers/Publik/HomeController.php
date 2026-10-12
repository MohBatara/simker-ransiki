<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Enums\HeroFocus;
use App\Models\HeroSlide;
use App\Models\Post;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\PublicDirectory;
use App\Support\PublicImages;
use Illuminate\Support\Collection;
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
            'sectors' => $sectors = PublicDirectory::sectors(),
            'types' => PublicDirectory::institutionTypes(),
            'settings' => $settings = SiteSetting::values(),
            'typePhoto' => $this->typePhoto($settings, $sectors),
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
     * Foto untuk satu kartu di bagian Jenis kelembagaan: foto sampul dari Konten Website, atau foto bidang usaha
     * yang diunggah Admin. Tidak pernah foto produk kelompok.
     *
     * @return array{image: array, caption: string}|null
     */
    private function typePhoto(array $settings, Collection $sectors): ?array
    {
        if ($cover = PublicImages::slot('sampul')) {
            return ['image' => $cover, 'caption' => rtrim((string) ($settings['cover_caption'] ?? 'Distrik Ransiki'), '. ')];
        }

        $sector = $sectors->first(fn ($sector) => ($sector->photo['kind'] ?? null) === 'sector');

        return $sector ? ['image' => PublicImages::sector($sector), 'caption' => 'Bidang usaha '.mb_strtolower($sector->name)] : null;
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
