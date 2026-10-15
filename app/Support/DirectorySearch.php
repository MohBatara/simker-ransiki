<?php

namespace App\Support;

use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Organization;
use App\Models\Village;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pencarian dan filter Direktori publik (satu tempat untuk hasil, facet, chip filter, dan judul SEO).
 * Selalu berangkat dari Organization::publiclyListed(). Dalam satu dimensi pilihan digabung ATAU, antardimensi DAN.
 * Query string: q, jenis[], bidang[], kampung[], urut, page; nilai tunggal lama (?bidang=5) tetap diterima.
 */
class DirectorySearch
{
    public const PER_PAGE = 12;

    public const DIMENSIONS = ['jenis' => 'Jenis kelembagaan', 'bidang' => 'Bidang usaha', 'kampung' => 'Kampung'];

    public const SORTS = ['nama' => 'Nama A ke Z', 'terbaru' => 'Terbaru', 'produk' => 'Produk terbanyak'];

    /** Kolom publik saja (CLAUDE.md Bagian 3): kontak, anggota, dan data internal tidak pernah diambil. */
    private const COLUMNS = ['organizations.id', 'organizations.slug', 'organizations.name', 'organizations.institution_type_id',
        'organizations.village_id', 'organizations.status', 'organizations.profile_status'];

    /** @var array<string, array<int, string>>|null */
    private ?array $options = null;

    /**
     * @param  array{jenis: list<int>, bidang: list<int>, kampung: list<int>}  $selected
     */
    public function __construct(public readonly string $q, public readonly array $selected, public readonly string $sort) {}

    public static function fromRequest(Request $request): self
    {
        $ids = fn (string $key) => collect(Arr::wrap($request->query($key)))
            ->filter(fn ($value) => is_scalar($value))
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $id) => $id > 0)
            ->unique()->sort()->values()->all();
        $sort = (string) $request->query('urut');

        return new self(
            mb_substr(trim((string) (is_scalar($request->query('q')) ? $request->query('q') : '')), 0, 100),
            ['jenis' => $ids('jenis'), 'bidang' => $ids('bidang'), 'kampung' => $ids('kampung')],
            array_key_exists($sort, self::SORTS) ? $sort : 'nama',
        );
    }

    /** Kelompok publik yang cocok dengan pencarian dan filter, kecuali dimensi $except (untuk hitungan facet). */
    public function filtered(?string $except = null): Builder
    {
        $selected = $this->selected;

        return Organization::query()->publiclyListed()
            ->when($this->q !== '', function (Builder $query) {
                $like = '%'.addcslashes($this->q, '\\%_').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('organizations.name', 'like', $like)
                    ->orWhereHas('products', fn ($query) => $query->where('is_public', true)->where('name', 'like', $like))
                    ->orWhereHas('village', fn ($query) => $query->where('name', 'like', $like)));
            })
            ->when($except !== 'jenis' && $selected['jenis'], fn ($query) => $query->whereIn('organizations.institution_type_id', $selected['jenis']))
            ->when($except !== 'kampung' && $selected['kampung'], fn ($query) => $query->whereIn('organizations.village_id', $selected['kampung']))
            ->when($except !== 'bidang' && $selected['bidang'], fn ($query) => $query->whereIn('organizations.id', DB::table('organization_sector')
                ->whereIn('business_sector_id', $selected['bidang'])->select('organization_id')));
    }

    public function results(): LengthAwarePaginator
    {
        $query = $this->filtered()
            ->select(self::COLUMNS)
            ->withCount(['products as public_products_count' => fn ($query) => $query->where('is_public', true)])
            ->with([
                'institutionType:id,name',
                'village:id,name',
                'sectors' => fn ($query) => $query->orderBy('name')->select(['business_sectors.id', 'business_sectors.name', 'business_sectors.photo_path']),
                // Foto kartu: foto produk publik terbaru.
                'products' => fn ($query) => $query->where('is_public', true)->whereNotNull('photo_path')->latest('id')->select(['id', 'organization_id', 'photo_path']),
            ]);

        match ($this->sort) {
            'terbaru' => $query->orderByDesc('organizations.created_at')->orderByDesc('organizations.id'),
            'produk' => $query->orderByDesc('public_products_count')->orderBy('organizations.name'),
            default => $query->orderBy('organizations.name'),
        };

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Opsi tiap dimensi (semua data master, urut nama) dengan jumlah kelompok unik yang cocok bila opsi itu dipilih,
     * mengikuti filter dimensi lain dan pencarian. Satu query agregasi per dimensi.
     *
     * @return array<string, Collection<int, object{id: int, name: string, count: int, checked: bool}>>
     */
    public function facets(): array
    {
        $counts = [
            'jenis' => $this->filtered('jenis')->groupBy('organizations.institution_type_id')
                ->selectRaw('organizations.institution_type_id as facet_id, COUNT(DISTINCT organizations.id) as total')->pluck('total', 'facet_id'),
            'kampung' => $this->filtered('kampung')->groupBy('organizations.village_id')
                ->selectRaw('organizations.village_id as facet_id, COUNT(DISTINCT organizations.id) as total')->pluck('total', 'facet_id'),
            'bidang' => DB::table('organization_sector')
                ->whereIn('organization_id', $this->filtered('bidang')->select('organizations.id'))
                ->groupBy('business_sector_id')
                ->selectRaw('business_sector_id as facet_id, COUNT(DISTINCT organization_id) as total')->pluck('total', 'facet_id'),
        ];

        return collect($this->options())->map(fn (array $names, string $dimension) => collect($names)->map(fn (string $name, int $id) => (object) [
            'id' => $id,
            'name' => $name,
            'count' => (int) ($counts[$dimension][$id] ?? 0),
            'checked' => in_array($id, $this->selected[$dimension], true),
        ])->values())->all();
    }

    /** @return array<string, array<int, string>> id => nama, per dimensi */
    public function options(): array
    {
        return $this->options ??= [
            'jenis' => InstitutionType::orderBy('name')->pluck('name', 'id')->all(),
            'bidang' => BusinessSector::orderBy('name')->pluck('name', 'id')->all(),
            'kampung' => Village::orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    public function activeFilterCount(): int
    {
        return collect($this->selected)->sum(fn (array $ids) => count($ids));
    }

    /** Parameter query string (tanpa nilai bawaan), untuk tautan chip dan URL kanonis. */
    public function params(?string $dimension = null, ?int $without = null, bool $withSearch = true): array
    {
        $selected = $this->selected;
        if ($dimension) {
            $selected[$dimension] = array_values(array_diff($selected[$dimension], [$without]));
        }

        return array_filter([
            'q' => $withSearch && $this->q !== '' ? $this->q : null,
            ...$selected,
            'urut' => $withSearch && $this->sort !== 'nama' ? $this->sort : null,
        ]);
    }

    /**
     * Chip filter aktif: label + URL tanpa filter itu (pencarian dan urutan tetap).
     *
     * @return list<array{label: string, url: string}>
     */
    public function chips(): array
    {
        $options = $this->options();

        return collect($this->selected)->flatMap(fn (array $ids, string $dimension) => collect($ids)
            ->filter(fn (int $id) => isset($options[$dimension][$id]))
            ->map(fn (int $id) => ['label' => $options[$dimension][$id], 'url' => route('public.directory', $this->params($dimension, $id))]))
            ->values()->all();
    }

    /** Judul halaman, mis. "Kelompok Perkebunan di Distrik Ransiki", "KUB di Kampung Contoh 1". */
    public function title(): string
    {
        $options = $this->options();
        $names = fn (string $dimension) => collect($this->selected[$dimension])->map(fn (int $id) => $options[$dimension][$id] ?? null)->filter();
        $join = fn (Collection $names) => $names->count() > 1 ? $names->slice(0, -1)->join(', ').' dan '.$names->last() : $names->first();

        if (! $this->selected['jenis'] && ! $this->selected['bidang'] && ! $this->selected['kampung']) {
            return $this->q !== '' ? 'Pencarian "'.$this->q.'" di Direktori kelompok' : 'Direktori kelompok';
        }

        $subject = $names('jenis')->isNotEmpty() ? $join($names('jenis')) : 'Kelompok';
        $sector = $names('bidang')->isNotEmpty() ? ' '.$join($names('bidang')) : '';
        $place = $names('kampung')->isNotEmpty() ? $join($names('kampung')) : 'Distrik Ransiki';

        return "{$subject}{$sector} di {$place}";
    }

    public function description(int $total): string
    {
        return $this->title() === 'Direktori kelompok'
            ? 'Temukan kelompok usaha, koperasi, dan BUMDes di Distrik Ransiki berdasarkan nama, produk, jenis, bidang usaha, atau kampung.'
            : Format::number($total).' kelompok ditemukan: '.$this->title().'. Profil kelompok, produk, dan bidang usaha dari Direktori kelompok Distrik Ransiki.';
    }

    /** URL kanonis: filter saja (tanpa pencarian dan urutan), halaman > 1 ikut. */
    public function canonical(int $page): string
    {
        return route('public.directory', [...$this->params(withSearch: false), ...($page > 1 ? ['page' => $page] : [])]);
    }

    /** Nama dengan kata kunci disorot (<mark>); teks lain di-escape. */
    public function highlight(string $name): string
    {
        if ($this->q === '') {
            return e($name);
        }

        $parts = preg_split('/('.preg_quote($this->q, '/').')/iu', $name, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$name];

        return collect($parts)->map(fn (string $part, int $i) => $i % 2 === 1
            ? '<mark class="rounded-sm bg-highlight text-ink">'.e($part).'</mark>'
            : e($part))->implode('');
    }
}
