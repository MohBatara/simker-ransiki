<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalur mengubah status kelompok yang sudah ada (Admin, menu "…" di detail kelompok).
 * Setiap perubahan dicatat di organization_status_changes: siapa, kapan, dari → ke, dan alasannya.
 */
class ChangeOrganizationStatus
{
    public function handle(User $actor, Organization $organization, OrganizationStatus $to, string $reason): Organization
    {
        return DB::transaction(function () use ($actor, $organization, $to, $reason) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $from = $organization->status;

            if ($from === $to) {
                return $organization;
            }

            $organization->forceFill(['status' => $to, 'updated_by' => $actor->id])->save();
            $organization->statusChanges()->create([
                'user_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => trim($reason),
            ]);

            return $organization;
        });
    }
}
