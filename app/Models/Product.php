<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Models\Concerns\HasAttachments;
use App\Observers\ChangeLogObserver;
use App\Support\ImageVariants;
use App\Support\Initials;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'description', 'unit', 'capacity', 'is_featured', 'is_public', 'photo_path'])]
#[ObservedBy(ChangeLogObserver::class)]
class Product extends Model
{
    use HasAttachments, HasFactory;

    /** Foto disimpan privat dan dibuka lewat route /foto-produk/{id} yang memeriksa hak akses. */
    public const PHOTO_DISK = 'local';

    public const THUMBNAIL_SUFFIX = '-kecil';

    protected $attributes = [
        'is_featured' => false,
        'is_public' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (Product $product) => $product->deletePhotoFiles());
    }

    public function thumbnailPath(): ?string
    {
        return $this->photo_path ? preg_replace('/\.jpg$/', self::THUMBNAIL_SUFFIX.'.jpg', $this->photo_path) : null;
    }

    public function deletePhotoFiles(): void
    {
        if ($this->photo_path) {
            Storage::disk(self::PHOTO_DISK)->delete($this->thumbnailPath());
            ImageVariants::delete(self::PHOTO_DISK, $this->photo_path, ImageVariants::PRODUCT);
        }
    }

    /**
     * Sumber <picture> untuk kartu publik: WebP 400/800 + JPEG 400 cadangan. Null bila belum ada foto.
     *
     * @return array{webp: array<int, string>, fallback: string}|null
     */
    public function photoSources(): ?array
    {
        if (! $this->photo_path) {
            return null;
        }

        $version = substr(md5($this->photo_path), 0, 8);

        return [
            'webp' => collect(ImageVariants::PRODUCT)->mapWithKeys(fn (int $width) => [
                $width => route('product-photos.show', ['product' => $this->id, 'ukuran' => $width, 'format' => 'webp', 'v' => $version]),
            ])->all(),
            'fallback' => $this->photoUrl(thumbnail: true),
        ];
    }

    /** Parameter v berubah setiap foto diganti, sehingga cache browser untuk foto lama tidak dipakai. */
    public function photoUrl(bool $thumbnail = false): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return route('product-photos.show', array_filter([
            'product' => $this->id,
            'ukuran' => $thumbnail ? 'kecil' : null,
            'v' => substr(md5($this->photo_path), 0, 8),
        ]));
    }

    /** Produk yang boleh tampil di website publik: produk publik milik kelompok yang terdaftar publik. */
    public function scopePubliclyListed(Builder $query): void
    {
        $query->where('products.is_public', true)
            ->whereIn('products.organization_id', Organization::query()->publiclyListed()->select('organizations.id'));
    }

    /** Inisial untuk petak pengganti foto, mis. "Kakao kering" menjadi "KK". */
    public function initials(): string
    {
        return Initials::of($this->name);
    }

    /** Foto boleh dilihat tanpa login hanya bila produk dan kelompoknya tampil di direktori publik. */
    public function isPubliclyVisible(): bool
    {
        $organization = $this->organization()->first(['id', 'status', 'is_public']);

        return $this->is_public
            && $organization?->is_public
            && $organization->status === OrganizationStatus::Active;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reportItems(): HasMany
    {
        return $this->hasMany(ReportItem::class);
    }
}
