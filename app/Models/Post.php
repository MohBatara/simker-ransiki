<?php

namespace App\Models;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use App\Support\ImageVariants;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Berita & Kegiatan. Isi berupa Markdown yang dirender aman lewat App\Support\Format::markdown.
 */
#[Fillable(['title', 'slug', 'category', 'summary', 'body', 'status', 'published_at', 'event_date', 'photo_caption', 'photo_alt'])]
class Post extends Model
{
    use HasFactory;

    /** Foto sampul disimpan privat dan dibuka lewat route /foto-berita/{id}. */
    public const PHOTO_DISK = 'local';

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'category' => PostCategory::class,
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'event_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (Post $post) => ImageVariants::delete(self::PHOTO_DISK, $post->cover_photo_path, ImageVariants::WIDE));
    }

    /**
     * Satu-satunya pintu berita ke website publik: berstatus Terbit dan tanggal terbitnya sudah lewat (WIT).
     */
    public function scopePubliclyListed(Builder $query): void
    {
        $query->where('posts.status', PostStatus::Published)
            ->whereNotNull('posts.published_at')
            ->where('posts.published_at', '<=', now());
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === PostStatus::Published && $this->published_at !== null && $this->published_at->lte(now());
    }

    /** Terbit tetapi tanggalnya masih di depan. */
    public function isScheduled(): bool
    {
        return $this->status === PostStatus::Published && $this->published_at?->isFuture();
    }

    /** Filter daftar Admin: q (judul), kategori, status (draft/terjadwal/terbit). */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when(trim((string) ($filters['q'] ?? '')), fn ($query, $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->when(PostCategory::tryFrom((string) ($filters['kategori'] ?? '')), fn ($query, $category) => $query->where('category', $category));

        match ($filters['status'] ?? null) {
            'draf' => $query->where('status', PostStatus::Draft),
            'terjadwal' => $query->where('status', PostStatus::Published)->where('published_at', '>', now()),
            'terbit' => $query->publiclyListed(),
            default => null,
        };
    }

    public static function uniqueSlug(string $text, ?int $ignoreId = null): string
    {
        $base = Str::slug($text) ?: 'berita';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** Tanggal di blok kalender Agenda: tanggal kegiatan bila diisi, selain itu tanggal terbit. */
    public function eventDay(): ?CarbonInterface
    {
        return $this->event_date ?? $this->published_at;
    }

    /** Perkiraan waktu baca dalam menit: jumlah kata isi / 200, minimal 1. */
    public function readingMinutes(): int
    {
        $words = preg_split('/\s+/u', trim(strip_tags((string) $this->body)), flags: PREG_SPLIT_NO_EMPTY);

        return max(1, (int) ceil(count($words) / 200));
    }

    /**
     * Sumber <picture> foto sampul (WebP 480/960/1600 + JPEG), atau null.
     *
     * @return array{webp: array<int, string>, fallback: string}|null
     */
    public function coverSources(): ?array
    {
        if (! $this->cover_photo_path) {
            return null;
        }

        $version = substr(md5($this->cover_photo_path), 0, 8);

        return [
            'webp' => collect(ImageVariants::WIDE)->mapWithKeys(fn (int $width) => [
                $width => route('public.post-photo', ['post' => $this->id, 'ukuran' => $width, 'format' => 'webp', 'v' => $version]),
            ])->all(),
            'fallback' => route('public.post-photo', ['post' => $this->id, 'v' => $version]),
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
