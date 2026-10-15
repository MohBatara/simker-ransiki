<?php

namespace App\Http\Controllers\Publik;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\Product;
use App\Support\Format;
use App\Support\ProductSearch;
use App\Support\PublicDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Katalog dan detail produk publik. Hanya Product::publiclyListed(); kolom kelompok yang diambil hanya kolom publik,
 * kontak (telepon) baru diambil bila kelompok mengizinkan.
 */
class ProductController extends Controller
{
    /** Kolom kartu produk (juga dipakai Beranda). */
    public const COLUMNS = ProductSearch::COLUMNS;

    private const RELATED_LIMIT = 4;

    public function index(Request $request): View
    {
        $search = ProductSearch::fromRequest($request);
        $products = $search->results();

        return view('publik.products.index', [
            'search' => $search,
            'products' => $products,
            'featured' => $search->featured($products->currentPage()),
            'sectors' => $search->sectors(),
            'allCount' => $search->allCount(),
            'villages' => $search->villages(),
            'total' => PublicDirectory::stats()['products'],
        ]);
    }

    public function show(string $slug): View
    {
        $product = Product::query()->publiclyListed()
            ->where('products.slug', $slug)
            ->with([
                'organization:id,slug,name,village_id,institution_type_id,status,profile_status,show_contact_publicly',
                'organization.village:id,name',
                'organization.institutionType:id,name',
                'organization.sectors:id,name',
                'publicPhotos' => fn ($query) => $query->orderBy('id')->select(['id', 'attachable_type', 'attachable_id', 'path']),
            ])
            ->firstOrFail(['products.id', 'products.organization_id', 'products.slug', 'products.name', 'products.description',
                'products.unit', 'products.capacity', 'products.is_featured', 'products.photo_path', 'products.updated_at']);

        $organization = $product->organization;
        // Telepon hanya diambil bila kelompok mengizinkan kontak tampil publik.
        $phone = $organization->show_contact_publicly
            ? Organization::query()->whereKey($organization->id)->value('phone')
            : null;

        $siblings = Product::query()->publiclyListed()
            ->where('products.organization_id', $organization->id)
            ->whereKeyNot($product->id)
            ->with(ProductSearch::CARD_RELATIONS)
            ->orderByDesc('products.is_featured')->orderByRaw('products.photo_path is null')->orderBy('products.name')
            ->limit(self::RELATED_LIMIT)
            ->get(self::COLUMNS);

        $sector = $organization->sectors->sortBy('name')->first();
        $photos = $this->photos($product);
        $description = $product->description
            ? Str::limit(preg_replace('/\s+/', ' ', $product->description), 160)
            : "{$product->name} dari {$organization->name}, {$organization->village->name}, Distrik Ransiki.";

        return view('publik.products.show', [
            'product' => $product,
            'organization' => $organization,
            'sector' => $sector,
            'photos' => $photos,
            'description' => $description,
            'ogImage' => $photos[0]['full'] ?? null,
            // Schema.org Product TANPA harga/offer: SIM-KER bukan toko.
            'jsonLd' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'description' => $product->description ? preg_replace('/\s+/', ' ', $product->description) : $description,
                'image' => array_column($photos, 'full') ?: null,
                'brand' => ['@type' => 'Brand', 'name' => $organization->name],
                'category' => $sector?->name,
                'url' => route('public.product', $product->slug),
            ]),
            'whatsapp' => Format::whatsappUrl($phone),
            'phone' => $phone,
            'siblings' => $siblings,
            'similar' => $this->similar($product),
            'inactive' => $organization->status === OrganizationStatus::Inactive,
        ]);
    }

    /** Foto lampiran publik produk (foto tambahan di galeri detail). */
    public function photo(Product $product, Attachment $attachment): StreamedResponse
    {
        abort_unless(
            $product->isPubliclyVisible()
                && $product->publicPhotos()->whereKey($attachment->id)->exists()
                && Storage::disk($attachment->disk)->exists($attachment->path),
            404,
        );

        return Storage::disk($attachment->disk)->response($attachment->path, null, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /**
     * Foto galeri: foto utama produk lalu lampiran gambar publik. Tiap foto: sumber <picture> untuk tampilan utama dan
     * URL besar untuk tampilan perbesar.
     *
     * @return list<array{image: array, full: string, thumb: string}>
     */
    private function photos(Product $product): array
    {
        $photos = [];
        if ($product->photo_path) {
            $photos[] = ['image' => [...$product->photoSources(), 'fallback' => $product->photoUrl()], 'full' => $product->photoUrl(), 'thumb' => $product->photoUrl(thumbnail: true)];
        }
        foreach ($product->publicPhotos as $attachment) {
            $url = route('public.product-photo', ['product' => $product->id, 'attachment' => $attachment->id]);
            $photos[] = ['image' => ['webp' => [], 'fallback' => $url], 'full' => $url, 'thumb' => $url];
        }

        return $photos;
    }

    /** Produk serupa: bidang usaha yang sama dari kelompok lain. Pilihan distrik dan yang berfoto dulu. */
    private function similar(Product $product): Collection
    {
        $sectors = $product->organization->sectors->pluck('id');
        if ($sectors->isEmpty()) {
            return collect();
        }

        return Product::query()->publiclyListed()
            ->where('products.organization_id', '!=', $product->organization_id)
            ->whereHas('organization.sectors', fn ($query) => $query->whereIn('business_sectors.id', $sectors))
            ->with(ProductSearch::CARD_RELATIONS)
            ->orderByDesc('products.is_featured')->orderByRaw('products.photo_path is null')->orderBy('products.name')
            ->limit(self::RELATED_LIMIT)
            ->get(self::COLUMNS);
    }
}
