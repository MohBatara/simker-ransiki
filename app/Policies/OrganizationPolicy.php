<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByOrganization;
use Illuminate\Auth\Access\Response;

class OrganizationPolicy
{
    use AuthorizesByOrganization;

    /** Daftar kelompok untuk admin dan OPD; isinya selalu disaring dengan scope visibleTo. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOpd();
    }

    public function view(User $user, Organization $organization): Response
    {
        return $this->organizationAccess($user, $organization->id);
    }

    /** Profil kelompok: admin, OPD pembina, dan kelompok itu sendiri. */
    public function update(User $user, Organization $organization): Response
    {
        return $this->organizationAccess($user, $organization->id);
    }

    /** Status, relasi OPD/bidang usaha, dan awal wajib lapor hanya diatur admin. */
    public function administer(User $user, Organization $organization): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->isAdmin();
    }
}
