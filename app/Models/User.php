<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['name', 'username', 'email', 'phone', 'password', 'must_change_password', 'role', 'opd_id', 'organization_id', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'is_active' => true,
        'must_change_password' => true,
    ];

    /** @var array<int, bool> Cache hasil cek akses per kelompok selama satu request. */
    private array $organizationAccess = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Username selalu disimpan dalam huruf kecil tanpa spasi di tepi. */
    protected function username(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : Str::lower(trim($value)));
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isOpd(): bool
    {
        return $this->role === Role::Opd;
    }

    public function isKelompok(): bool
    {
        return $this->role === Role::Kelompok;
    }

    /** Akun OPD wajib punya opd_id dan akun kelompok wajib punya organization_id. */
    public function isLinked(): bool
    {
        return match ($this->role) {
            Role::Admin => true,
            Role::Opd => $this->opd_id !== null,
            Role::Kelompok => $this->organization_id !== null,
        };
    }

    /**
     * Sumber kebenaran hak lihat kelompok; harus selalu sejalan dengan Organization::scopeVisibleTo().
     */
    public function canAccessOrganization(int $organizationId): bool
    {
        return $this->organizationAccess[$organizationId] ??= match ($this->role) {
            Role::Admin => true,
            Role::Opd => $this->opd_id !== null && DB::table('organization_opd')
                ->where('organization_id', $organizationId)
                ->where('opd_id', $this->opd_id)
                ->exists(),
            Role::Kelompok => $this->organization_id === $organizationId,
        };
    }
}
