<?php

namespace App\Models;

use App\Enums\LegalStatus;
use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\Concerns\HasAttachments;
use App\Support\SimilarNames;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;
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

    /**
     * Kelompok yang boleh tampil di website publik: aktif dan dipublikasikan. Satu-satunya pintu
     * data kelompok ke halaman publik, sitemap, dan Open Graph.
     */
    public function scopePubliclyListed(Builder $query): void
    {
        $query->where('organizations.status', OrganizationStatus::Active)->where('organizations.is_public', true);
    }

    /**
     * Filter daftar kelompok dari query string: q, type, village, sector, opd ("none" = tanpa OPD), status.
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when(trim((string) ($filters['q'] ?? '')), fn ($query, $search) => $query->where('organizations.name', 'like', '%'.$search.'%'))
            ->when((int) ($filters['type'] ?? 0), fn ($query, $id) => $query->where('institution_type_id', $id))
            ->when((int) ($filters['village'] ?? 0), fn ($query, $id) => $query->where('village_id', $id))
            ->when((int) ($filters['sector'] ?? 0), fn ($query, $id) => $query->whereHas('sectors', fn ($query) => $query->whereKey($id)))
            ->when(OrganizationStatus::tryFrom((string) ($filters['status'] ?? '')), fn ($query, $status) => $query->where('status', $status));

        if (($filters['akun'] ?? null) === 'tidak-ada') {
            $query->doesntHave('users');
        }

        // Tujuan klik dari dasbor: kelompok wajib lapor yang belum membuat / belum mengajukan laporan.
        $period = $filters['periode'] ?? null;
        if (in_array($filters['laporan'] ?? null, ['belum-dibuat', 'belum-diajukan'], true) && is_string($period) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $date = CarbonImmutable::createFromFormat('!Y-m', $period);
            $submittedOnly = $filters['laporan'] === 'belum-diajukan';

            $query->obligatedFor($date)->whereDoesntHave('monthlyReports', fn ($query) => $query
                ->whereDate('period', $date->format('Y-m-d'))
                ->when($submittedOnly, fn ($query) => $query->where('status', '!=', ReportStatus::Draft)));
        }

        $opd = $filters['opd'] ?? null;
        if ($opd === 'none') {
            $query->doesntHave('opds');
        } elseif ((int) $opd) {
            $query->whereHas('opds', fn ($query) => $query->whereKey((int) $opd));
        }
    }

    /**
     * Kelompok lain di kampung yang sama dengan nama mirip (lihat App\Support\SimilarNames).
     */
    public static function similarTo(string $name, int $villageId, ?int $exceptId = null): Collection
    {
        return static::query()
            ->where('village_id', $villageId)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (Organization $other) => SimilarNames::areSimilar($name, $other->name))
            ->values();
    }

    /**
     * Kelompok yang wajib melapor untuk suatu periode: aktif atau tidak aktif, dan kewajiban lapornya
     * sudah dimulai. Kelompok tutup tidak dihitung (CLAUDE.md Bagian 5).
     */
    public function scopeObligatedFor(Builder $query, CarbonInterface $period): void
    {
        $query->whereIn('organizations.status', [OrganizationStatus::Active, OrganizationStatus::Inactive])
            ->whereDate('organizations.reporting_starts_on', '<=', $period->format('Y-m-01'));
    }

    /**
     * Periode wajib lapor dari reporting_starts_on sampai bulan berjalan, terbaru dulu.
     *
     * @return SupportCollection<int, CarbonImmutable>
     */
    public function reportingPeriods(): SupportCollection
    {
        $start = CarbonImmutable::parse($this->reporting_starts_on)->startOfMonth();
        $periods = collect();

        for ($period = now()->toImmutable()->startOfMonth(); $period->gte($start); $period = $period->subMonthNoOverflow()) {
            $periods->push($period);
        }

        return $periods;
    }

    public function acceptsReportFor(CarbonInterface $period): bool
    {
        $period = CarbonImmutable::parse($period)->startOfMonth();

        return $period->gte(CarbonImmutable::parse($this->reporting_starts_on)->startOfMonth())
            && $period->lte(now()->startOfMonth());
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
