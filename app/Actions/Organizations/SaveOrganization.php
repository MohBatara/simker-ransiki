<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan profil kelompok beserta bidang usaha dan OPD terkait. Dipakai Admin, OPD, dan Kelompok;
 * kolom yang boleh diubah sudah disaring oleh OrganizationRequest.
 */
class SaveOrganization
{
    public function handle(User $actor, array $data, ?Organization $organization = null): Organization
    {
        return DB::transaction(function () use ($actor, $data, $organization) {
            $organization ??= new Organization;
            $organization->fill(Arr::except($data, ['sectors', 'opds']));
            $organization->forceFill([
                'created_by' => $organization->exists ? $organization->created_by : $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            if (array_key_exists('sectors', $data)) {
                $organization->sectors()->sync($data['sectors']);
            }
            if (array_key_exists('opds', $data)) {
                $organization->opds()->sync($data['opds']);
            }

            return $organization;
        });
    }
}
