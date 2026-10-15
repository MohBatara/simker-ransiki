<?php

namespace App\Support;

use App\Enums\PostCategory;
use App\Http\Controllers\Publik\PostController;
use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Halaman Berita (/berita): satu tempat untuk daftar terbaru, sorotan, agenda kegiatan, arsip bulan, dan jumlah per
 * kategori. Selalu dari Post::publiclyListed() (Terbit, tanggal terbit ≤ sekarang WIT).
 * Query string: kategori (berita/kegiatan), q (judul), bulan (YYYY-MM), page.
 */
class PostListing
{
    public const PER_PAGE = 10;

    public const HIGHLIGHTS = 3;

    public const AGENDA_LIMIT = 8;

    /** @var Collection<int, Post>|null */
    private ?Collection $highlights = null;

    public function __construct(
        public readonly ?PostCategory $category,
        public readonly string $q,
        public readonly ?Carbon $month,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $scalar = fn (string $key) => is_scalar($request->query($key)) ? trim((string) $request->query($key)) : '';
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $scalar('bulan')) ? Carbon::createFromFormat('!Y-m', $scalar('bulan'))->startOfMonth() : null;

        return new self(PostCategory::tryFrom($scalar('kategori')), Str::limit($scalar('q'), 100, ''), $month);
    }

    public function isFiltered(): bool
    {
        return $this->category || $this->q !== '' || $this->month;
    }

    /** Post publik yang cocok dengan cari dan bulan; $withCategory = false untuk jumlah per kategori. */
    public function filtered(bool $withCategory = true): Builder
    {
        return Post::query()->publiclyListed()
            ->when($this->q !== '', fn (Builder $query) => $query->where('posts.title', 'like', '%'.addcslashes($this->q, '\\%_').'%'))
            ->when($this->month, fn (Builder $query, Carbon $month) => $query->whereBetween('posts.published_at', [$month, $month->copy()->endOfMonth()]))
            ->when($withCategory && $this->category, fn (Builder $query) => $query->where('posts.category', $this->category));
    }

    /**
     * Sorotan (tanpa filter/cari): berita utama = post terbaru berfoto (tanpa itu: terbaru), lalu dua post terbaru
     * lainnya. Dihitung di setiap halaman agar daftar terbaru tidak pernah mengulangnya; ditampilkan hanya di halaman 1.
     *
     * @return Collection<int, Post>
     */
    public function highlights(): Collection
    {
        if ($this->highlights !== null) {
            return $this->highlights;
        }
        if ($this->isFiltered()) {
            return $this->highlights = collect();
        }

        $latest = fn () => Post::query()->publiclyListed()->latest('posts.published_at')->latest('posts.id');
        $lead = $latest()->whereNotNull('posts.cover_photo_path')->first(PostController::COLUMNS) ?? $latest()->first(PostController::COLUMNS);

        return $this->highlights = $lead
            ? collect([$lead])->concat($latest()->whereKeyNot($lead->id)->limit(self::HIGHLIGHTS - 1)->get(PostController::COLUMNS))
            : collect();
    }

    public function results(): LengthAwarePaginator
    {
        return $this->filtered()
            ->whereKeyNot($this->highlights()->pluck('id')->all())
            ->latest('posts.published_at')->latest('posts.id')
            ->paginate(self::PER_PAGE, PostController::COLUMNS)
            ->withQueryString();
    }

    /**
     * Agenda kegiatan (halaman 1 tanpa filter): post kategori Kegiatan terbaru, maks AGENDA_LIMIT, dengan kelompok
     * terkait yang terdaftar publik (nama + kampung).
     *
     * @return Collection<int, Post>
     */
    public function agenda(int $page): Collection
    {
        if ($page > 1 || $this->isFiltered()) {
            return collect();
        }

        return Post::query()->publiclyListed()->where('posts.category', PostCategory::Activity)
            ->with(['organizations' => fn ($query) => $query->publiclyListed()->orderBy('name')
                ->select(['organizations.id', 'organizations.name', 'organizations.village_id'])->with('village:id,name')])
            ->latest('posts.published_at')->latest('posts.id')
            ->limit(self::AGENDA_LIMIT)
            ->get(PostController::COLUMNS);
    }

    /** @return array<string, int> jumlah per kategori ('' = semua), mengikuti cari dan bulan */
    public function counts(): array
    {
        $counts = $this->filtered(withCategory: false)->groupBy('posts.category')->selectRaw('posts.category, COUNT(*) as total')->pluck('total', 'category');

        return ['' => (int) $counts->sum()] + collect(PostCategory::cases())
            ->mapWithKeys(fn (PostCategory $case) => [$case->value => (int) ($counts[$case->value] ?? 0)])->all();
    }

    /**
     * Arsip: bulan yang punya post publik (terbaru dulu) dengan jumlahnya, mengikuti kategori yang dipilih.
     *
     * @return list<array{value: string, label: string, count: int}>
     */
    public function archive(): array
    {
        return Post::query()->publiclyListed()
            ->when($this->category, fn (Builder $query) => $query->where('posts.category', $this->category))
            ->selectRaw("DATE_FORMAT(posts.published_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')->orderByDesc('month')
            ->get()
            ->map(fn ($row) => ['value' => $row->month, 'label' => Format::month(Carbon::createFromFormat('!Y-m', $row->month)), 'count' => (int) $row->total])
            ->all();
    }

    /** URL daftar dengan parameter saat ini, ditimpa $changes (null = dihapus). */
    public function url(array $changes = []): string
    {
        return route('public.posts', array_filter([
            'kategori' => $this->category?->value,
            'q' => $this->q !== '' ? $this->q : null,
            'bulan' => $this->month?->format('Y-m'),
            ...$changes,
        ]));
    }

    public function title(): string
    {
        $subject = $this->category?->label() ?? 'Berita dan kegiatan';

        return match (true) {
            $this->q !== '' => 'Pencarian "'.$this->q.'" di Berita',
            (bool) $this->month => $subject.' '.Format::month($this->month),
            (bool) $this->category => $subject.' Distrik Ransiki',
            default => 'Kabar Distrik Ransiki',
        };
    }

    /** URL kanonis: kategori dan bulan (tanpa cari), halaman > 1 ikut. */
    public function canonical(int $page): string
    {
        return route('public.posts', array_filter(['kategori' => $this->category?->value, 'bulan' => $this->month?->format('Y-m'), 'page' => $page > 1 ? $page : null]));
    }
}
