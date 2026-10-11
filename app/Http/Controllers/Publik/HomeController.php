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

    public function __invoke(): View
    {
        return view('publik.home', [
            'stats' => PublicDirectory::stats(),
            'sectors' => PublicDirectory::sectors(),
            'settings' => SiteSetting::values(),
            'cover' => PublicImages::slot('sampul'),
            'about' => PublicImages::slot('tentang'),
            'posts' => Post::query()->publiclyListed()->latest('published_at')->latest('id')->limit(3)->get(PostController::COLUMNS),
            ...$this->featured(),
        ]);
    }

    /**
     * Produk unggulan berfoto (maks 4). Bila belum ada yang berfoto, produk unggulan tampil sebagai kartu
     * ringkas. Produk bernama sama tidak ditaruh berdampingan.
     *
     * @return array{featured: Collection, featuredCompact: bool}
     */
    private function featured(): array
    {
        $query = fn () => Product::query()->publiclyListed()
            ->where('products.is_featured', true)
            ->with(['organization:id,slug,name', 'organization.sectors:id,name'])
            ->orderBy('products.name')
            ->limit(12);
        $columns = ['products.id', 'products.organization_id', 'products.name', 'products.unit', 'products.capacity', 'products.photo_path'];

        $withPhoto = $query()->whereNotNull('products.photo_path')->get($columns);
        $candidates = $withPhoto->isNotEmpty() ? $withPhoto : $query()->get($columns);

        return [
            'featured' => self::spreadSameNames($candidates)->take(self::FEATURED_LIMIT)->values(),
            'featuredCompact' => $withPhoto->isEmpty(),
        ];
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
