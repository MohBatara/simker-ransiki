<?php

namespace App\Models;

use App\Enums\LegalStatus;
use App\Enums\OrganizationStatus;
use App\Enums\Role;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'slug', 'name', 'institution_type_id', 'village_id', 'address', 'founded_year',
    'leader_name', 'phone', 'email', 'members_count', 'description',
    'production_capacity', 'markets', 'workforce_count', 'assets', 'facilities', 'equipment',
    'legal_status', 'legal_number', 'legal_notes',
    'status', 'reporting_starts_on', 'is_public', 'show_contact_publicly',
    'created_by', 'updated_by',
])]
class Organization extends Model
{
    use HasAttachments, HasFactory;

    protected $attributes = [
        'status' => 'active',
        'legal_status' => 'unknown',
        'is_public' => false,
        'show_contact_publicly' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'legal_status' => LegalStatus::class,
            'reporting_starts_on' => 'date',
            'founded_year' => 'integer',
            'members_count' => 'integer',
            'workforce_count' => 'integer',
            'is_public' => 'boolean',
            'show_contact_publicly' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            $organization->slug ??= static::uniqueSlug($organization->name);
        });

        static::saving(function (Organization $organization) {
            $organization->reporting_starts_on = ($organization->reporting_starts_on ?? now())->startOfMonth();
        });

        static::deleting(function (Organization $organization) {
            // Kelompok yang punya laporan atau akun tidak dihapus; ubah statusnya menjadi "closed".
            if ($organization->monthlyReports()->exists() || $organization->users()->exists()) {
                return false;
            }

            // Lewat Eloquent (bukan FK cascade) agar lampiran dan foto produknya ikut terhapus.
            $organization->products()->get()->each->delete();
            $organization->licenses()->get()->each->delete();
        });
    }

    /**
     * Kelompok yang boleh dilihat pengguna. Pakai untuk SEMUA daftar kelompok di area login.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        match ($user->role) {
            Role::Admin => null,
            // Subquery, bukan join, agar kelompok dengan banyak OPD tidak muncul ganda.
            Role::Opd => $query->whereIn('organizations.id', fn ($sub) => $sub
                ->select('organization_id')
                ->from('organization_opd')
                ->where('opd_id', $user->opd_id)),
            Role::Kelompok => $query->where('organizations.id', $user->organization_id),
        };
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->canAccessOrganization($this->id);
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'kelompok';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function institutionType(): BelongsTo
    {
        return $this->belongsTo(InstitutionType::class);
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(BusinessSector::class, 'organization_sector');
    }

    public function opds(): BelongsToMany
    {
        return $this->belongsToMany(Opd::class, 'organization_opd');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyReport::class);
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
