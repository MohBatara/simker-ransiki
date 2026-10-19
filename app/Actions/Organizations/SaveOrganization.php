<?php

namespace App\Actions\Organizations;

use App\Enums\ProfileStatus;
use App\Models\BusinessSector;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Support\ChangeLogger;
use App\Support\ProfileReview;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan profil kelompok beserta bidang usaha dan OPD terkait. Dipakai Admin, OPD, dan Kelompok;
 * kolom yang boleh diubah sudah disaring oleh OrganizationRequest. Perubahan kolom dicatat observer,
 * perubahan relasi dicatat di sini; field penting yang diubah kelompok memicu verifikasi ulang profil.
 * Kelompok yang DIBUAT petugas (Admin/OPD, termasuk impor) langsung berstatus "Profil terverifikasi" dan dicatat di
 * Riwayat; antrean verifikasi hanya untuk perubahan dari akun kelompok.
 */
class SaveOrganization
{
    public function handle(User $actor, array $data, ?Organization $organization = null): Organization
    {
        $flagged = false;

        $organization = DB::transaction(function () use ($actor, $data, $organization, &$flagged) {
            $organization ??= new Organization;
            $existing = $organization->exists;
            $sectorsBefore = $existing ? $organization->sectors()->pluck('name')->all() : [];
            $opdsBefore = $existing ? $organization->opds()->pluck('name')->all() : [];

            $organization->fill(Arr::except($data, ['sectors', 'opds']));

            if ($existing) {
                $sectorsAfter = array_key_exists('sectors', $data) ? BusinessSector::whereKey($data['sectors'])->pluck('name')->all() : $sectorsBefore;
                $changed = array_keys($organization->getDirty());
                if ($this->sameNames($sectorsBefore, $sectorsAfter) === false) {
                    $changed[] = 'sectors';
                }
                $flagged = ProfileReview::track($organization, $changed, $actor);
            }

            $autoVerified = ! $existing && ! $actor->isKelompok();
            $organization->forceFill([
                'created_by' => $existing ? $organization->created_by : $actor->id,
                'updated_by' => $actor->id,
                ...($autoVerified ? [
                    'profile_status' => ProfileStatus::Verified,
                    'profile_verified_at' => now(),
                    'profile_verified_by' => $actor->id,
                ] : []),
            ])->save();

            if ($autoVerified) {
                ChangeLogger::note($organization, 'profile_status', 'Diverifikasi otomatis saat dibuat oleh '.$actor->name);
            }

            if (array_key_exists('sectors', $data)) {
                $organization->sectors()->sync($data['sectors']);
                if ($existing) {
                    ChangeLogger::relation($organization, 'sectors', $sectorsBefore, $sectorsAfter);
                }
            }
            if (array_key_exists('opds', $data)) {
                $organization->opds()->sync($data['opds']);
                if ($existing) {
                    ChangeLogger::relation($organization, 'opds', $opdsBefore, Opd::whereKey($data['opds'])->pluck('name')->all());
                }
            }

            return $organization;
        });

        if ($flagged) {
            ProfileReview::notify($organization);
        }

        return $organization;
    }

    private function sameNames(array $before, array $after): bool
    {
        sort($before);
        sort($after);

        return $before === $after;
    }
}
