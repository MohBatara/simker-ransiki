<?php

namespace App\Support;

use App\Enums\ProfileStatus;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ProfileNeedsReview;
use Illuminate\Support\Facades\Notification;

/**
 * Verifikasi profil kelompok. Profil yang sudah diverifikasi menjadi "Perubahan menunggu verifikasi" bila akun
 * kelompok mengubah field penting; perubahan oleh Admin/OPD (yang memang berhak memverifikasi) tidak memicu.
 */
class ProfileReview
{
    /** Field penting => label untuk ringkasan di antrean. */
    public const FIELDS = [
        'name' => 'Nama',
        'institution_type_id' => 'Jenis',
        'village_id' => 'Kampung',
        'leader_name' => 'Ketua',
        'phone' => 'Telepon',
        'email' => 'Email',
        'address' => 'Alamat',
        'description' => 'Deskripsi',
        'sectors' => 'Bidang usaha',
    ];

    /**
     * Tandai profil perlu verifikasi ulang (belum disimpan). Field yang berubah ditambahkan ke daftar
     * sampai profil diverifikasi lagi.
     *
     * @param  list<string>  $changed  kolom yang berubah (termasuk "sectors")
     * @return bool true bila status baru saja berubah menjadi "Perubahan menunggu verifikasi"
     */
    public static function track(Organization $organization, array $changed, User $actor): bool
    {
        $important = array_values(array_intersect(array_keys(self::FIELDS), $changed));

        if (! $actor->isKelompok() || $important === [] || $organization->profile_status === ProfileStatus::Unverified) {
            return false;
        }

        $transition = $organization->profile_status === ProfileStatus::Verified;

        $organization->forceFill([
            'profile_status' => ProfileStatus::NeedsReview,
            'profile_changed_fields' => array_values(array_unique([...($organization->profile_changed_fields ?? []), ...$important])),
            'profile_changed_at' => now(),
        ]);

        return $transition;
    }

    public static function verify(Organization $organization, User $actor): void
    {
        $organization->forceFill([
            'profile_status' => ProfileStatus::Verified,
            'profile_verified_at' => now(),
            'profile_verified_by' => $actor->id,
            'profile_changed_fields' => null,
            'profile_changed_at' => null,
        ])->save();
    }

    /** @return list<string> label field yang berubah sejak verifikasi terakhir */
    public static function changedLabels(Organization $organization): array
    {
        return collect($organization->profile_changed_fields ?? [])->map(fn (string $field) => self::FIELDS[$field] ?? $field)->values()->all();
    }

    /** Ke semua akun OPD pembina yang aktif; kelompok tanpa OPD pembina → semua Admin aktif. */
    public static function notify(Organization $organization): void
    {
        $opdIds = $organization->opds()->pluck('opds.id');

        $recipients = User::query()->where('is_active', true)
            ->when(
                $opdIds->isNotEmpty(),
                fn ($query) => $query->where('role', Role::Opd)->whereIn('opd_id', $opdIds),
                fn ($query) => $query->where('role', Role::Admin),
            )
            ->get();

        Notification::send($recipients, new ProfileNeedsReview([
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'fields' => self::changedLabels($organization),
        ]));
    }
}
