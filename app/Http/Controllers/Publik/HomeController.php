<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
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
        $settings = SiteSetting::values();
        $sectors = PublicDirectory::sectors();

        return view('publik.home', [
            'stats' => PublicDirectory::stats(),
            'sectors' => $sectors,
            'types' => PublicDirectory::institutionTypes(),
            'settings' => $settings,
            'slides' => $this->slides($settings, $sectors),
            'about' => PublicImages::slot('tentang') ?? PublicImages::slot('sampul'),
            'posts' => Post::query()->publiclyListed()->latest('published_at')->latest('id')->limit(self::POST_LIMIT)->get(PostController::COLUMNS),
            'featured' => $this->featured(),
        ]);
    }

    /**
     * Foto hero (maks 3), hanya foto kurasi Admin: foto sampul, lalu foto bidang usaha dari Konten Website.
     *
     * @return list<array{image: array, alt: string, caption: ?string}>
     */
    private function slides(array $settings, Collection $sectors): array
    {
        // "Panen kakao." + "Nama" → "Panen kakao · Foto: Nama" (titik akhir keterangan dibuang).
        $coverCaption = collect([
            $settings['cover_credit'] ? rtrim((string) $settings['cover_caption'], '. ') : $settings['cover_caption'],
            $settings['cover_credit'] ? 'Foto: '.$settings['cover_credit'] : null,
        ])->filter()->join(' · ');

        $cover = PublicImages::slot('sampul');

        return collect($cover ? [['image' => $cover, 'alt' => (string) $settings['cover_caption'], 'caption' => $coverCaption ?: null]] : [])
            ->concat($sectors->filter(fn ($sector) => ($sector->photo['kind'] ?? null) === 'sector')->map(fn ($sector) => [
                'image' => PublicImages::sector($sector),
                'alt' => 'Bidang usaha '.mb_strtolower($sector->name),
                'caption' => 'Bidang usaha '.mb_strtolower($sector->name),
            ]))
            ->take(self::SLIDE_LIMIT)
            ->values()
            ->all();
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
