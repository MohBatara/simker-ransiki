<?php

namespace App\Support;

use App\Models\BusinessSector;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Village;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Katalog Produk unggulan (/produk): satu tempat untuk hasil, jumlah per bidang, pilihan kampung, deretan "Pilihan
 * distrik", dan judul SEO. Selalu berangkat dari Product::publiclyListed() (produk publik milik kelompok publik).
 * Query string: q (nama produk, nama kelompok, kampung), bidang, kampung (satu nilai), urut, page.
 */
class ProductSearch
{
    public const PER_PAGE = 16;

    public const FEATURED_LIMIT = 8;

    public const SORTS = ['pilihan' => 'Pilihan distrik dulu', 'terbaru' => 'Terbaru', 'nama' => 'Nama A ke Z'];

    /** Kolom publik produk untuk kartu. */
    public const COLUMNS = ['products.id', 'products.organization_id', 'products.slug', 'products.name', 'products.unit',
        'products.capacity', 'products.photo_path', 'products.is_featured'];

    /** Relasi kartu: kelompok (kolom publik saja), kampung, dan bidang usaha. */
    public const CARD_RELATIONS = ['organization:id,slug,name,village_id', 'organization.village:id,name', 'organization.sectors:id,name'];

    public function __construct(
        public readonly string $q,
        public readonly ?int $sector,
        public readonly ?int $village,
        public readonly string $sort,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $id = fn (string $key) => is_scalar($request->query($key)) && (int) $request->query($key) > 0 ? (int) $request->query($key) : null;
        $sort = (string) (is_scalar($request->query('urut')) ? $request->query('urut') : '');

        return new self(
            mb_substr(trim((string) (is_scalar($request->query('q')) ? $request->query('q') : '')), 0, 100),
            $id('bidang'),
            $id('kampung'),
            array_key_exists($sort, self::SORTS) ? $sort : 'pilihan',
        );
    }

    public function isFiltered(): bool
    {
        return $this->q !== '' || $this->sector || $this->village;
    }

    /** Produk publik yang cocok dengan pencarian dan filter; $withSector = false untuk jumlah per bidang. */
    public function filtered(bool $withSector = true): Builder
    {
        return Product::query()->publiclyListed()
            ->when($this->q !== '', function (Builder $query) {
                $like = '%'.addcslashes($this->q, '\\%_').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('products.name', 'like', $like)
                    ->orWhereIn('products.organization_id', Organization::query()
                        ->where('organizations.name', 'like', $like)
                        ->orWhereIn('organizations.village_id', Village::query()->where('name', 'like', $like)->select('id'))
                        ->select('organizations.id')));
            })
            ->when($this->village, fn (Builder $query, int $id) => $query->whereIn('products.organization_id', Organization::query()->where('village_id', $id)->select('id')))
            ->when($withSector && $this->sector, fn (Builder $query) => $query->whereIn('products.organization_id', DB::table('organization_sector')
                ->where('business_sector_id', $this->sector)->select('organization_id')));
    }

    public function results(): LengthAwarePaginator
    {
        $query = $this->filtered()->with(self::CARD_RELATIONS);

        match ($this->sort) {
            'terbaru' => $query->orderByDesc('products.created_at')->orderByDesc('products.id'),
            'nama' => $query->orderBy('products.name')->orderBy('products.id'),
            // Pilihan distrik dulu, lalu yang berfoto, lalu nama.
            default => $query->orderByDesc('products.is_featured')->orderByRaw('products.photo_path is null')->orderBy('products.name')->orderBy('products.id'),
        };

        return $query->paginate(self::PER_PAGE, self::COLUMNS)->withQueryString();
    }

    /**
     * Deretan "Pilihan distrik": hanya di halaman 1 tanpa pencarian/filter. Berfoto dulu, maks FEATURED_LIMIT.
     *
     * @return Collection<int, Product>
     */
    public function featured(int $page): Collection
    {
        if ($page > 1 || $this->isFiltered()) {
            return collect();
        }

        return Product::query()->publiclyListed()->where('products.is_featured', true)
            ->with(self::CARD_RELATIONS)
            ->orderByRaw('products.photo_path is null')->orderBy('products.name')
            ->limit(self::FEATURED_LIMIT)
            ->get(self::COLUMNS);
    }

    /**
     * Pil kategori: bidang usaha yang punya produk publik (urut nama), dengan jumlah produk unik yang cocok dengan
     * pencarian dan kampung bila bidang itu dipilih. Dua query agregasi (daftar tetap, jumlah mengikuti filter).
     *
     * @return Collection<int, object{id: int, name: string, count: int}>
     */
    public function sectors(): Collection
    {
        $count = fn (Builder $products) => DB::table('organization_sector')
            ->join('products', 'products.organization_id', '=', 'organization_sector.organization_id')
            ->whereIn('products.id', $products->select('products.id'))
            ->groupBy('organization_sector.business_sector_id')
            ->selectRaw('organization_sector.business_sector_id as sector_id, COUNT(DISTINCT products.id) as total')
            ->pluck('total', 'sector_id');

        $available = $count(Product::query()->publiclyListed());
        $counts = $this->isFiltered() ? $count($this->filtered(withSector: false)) : $available;

        return BusinessSector::query()->whereKey($available->keys())->orderBy('name')->get(['id', 'name'])
            ->map(fn (BusinessSector $sector) => (object) ['id' => $sector->id, 'name' => $sector->name, 'count' => (int) ($counts[$sector->id] ?? 0)]);
    }

    /** Jumlah produk untuk pil "Semua" (pencarian dan kampung ikut, bidang tidak). */
    public function allCount(): int
    {
        return $this->filtered(withSector: false)->distinct()->count('products.id');
    }

    /** @return array<int, string> kampung yang punya produk publik, id => nama */
    public function villages(): array
    {
        return Village::query()
            ->whereIn('id', Organization::query()->publiclyListed()
                ->whereIn('organizations.id', Product::query()->publiclyListed()->select('products.organization_id'))
                ->select('organizations.village_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function sectorName(): ?string
    {
        return $this->sector ? BusinessSector::query()->whereKey($this->sector)->value('name') : null;
    }

    public function villageName(): ?string
    {
        return $this->village ? Village::query()->whereKey($this->village)->value('name') : null;
    }

    /** Judul halaman, mis. "Produk Perkebunan di Kampung Contoh 1". */
    public function title(): string
    {
        $sector = $this->sectorName();
        $village = $this->villageName();

        if (! $sector && ! $village) {
            return $this->q !== '' ? 'Pencarian "'.$this->q.'" di Produk unggulan' : 'Produk unggulan';
        }

        return 'Produk'.($sector ? ' '.$sector : '').' di '.($village ?? 'Distrik Ransiki');
    }

    public function description(int $total): string
    {
        return $this->title() === 'Produk unggulan'
            ? 'Hasil kebun, laut, dan olahan kelompok usaha di Distrik Ransiki. Cari menurut nama produk, kelompok, bidang usaha, atau kampung.'
            : Format::number($total).' produk ditemukan: '.$this->title().'. Produk kelompok usaha dari katalog Produk unggulan Distrik Ransiki.';
    }

    /** URL kanonis: filter saja (tanpa pencarian dan urutan), halaman > 1 ikut. */
    public function canonical(int $page): string
    {
        return route('public.products', array_filter(['bidang' => $this->sector, 'kampung' => $this->village, 'page' => $page > 1 ? $page : null]));
    }

    public function highlight(string $name): string
    {
        return Format::highlight($name, $this->q);
    }
}
