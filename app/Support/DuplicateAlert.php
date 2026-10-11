<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\PossibleDuplicate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Notifikasi "Kemungkinan data ganda" ke semua Admin aktif (termasuk yang sedang menyimpan, sebagai pengingat
 * pemeriksaan). Pasangan yang sudah ditandai "Bukan data ganda" tidak pernah memicu notifikasi.
 */
class DuplicateAlert
{
    /**
     * @param  Collection<int, array>  $candidates  hasil DuplicateCandidates::for()
     * @param  string  $source  'tambah' atau 'ubah'
     */
    public static function organizationSaved(User $actor, Organization $organization, Collection $candidates, string $source): void
    {
        if ($candidates->isEmpty()) {
            return;
        }

        $first = $candidates->first();

        self::send([
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'candidate_name' => $first['name'],
            'strong' => $first['strong'],
            'others' => $candidates->count() - 1,
            'source' => $source,
            'actor_name' => $actor->name,
        ]);
    }

    /** Satu notifikasi ringkas untuk satu kali impor. @param  Collection<int, Organization>  $organizations */
    public static function imported(User $actor, Collection $organizations): void
    {
        if ($organizations->isEmpty()) {
            return;
        }

        $finder = new DuplicateCandidates;
        $count = $organizations->filter(fn (Organization $organization) => $finder->for($organization->name, $organization->village_id, $organization->id)->isNotEmpty())->count();

        if ($count > 0) {
            self::send(['source' => 'impor', 'count' => $count, 'actor_name' => $actor->name]);
        }
    }

    private static function send(array $data): void
    {
        Notification::send(User::query()->where('role', Role::Admin)->where('is_active', true)->get(), new PossibleDuplicate($data));
    }
}
