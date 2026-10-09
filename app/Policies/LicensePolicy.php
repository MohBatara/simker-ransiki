<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\License;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByOrganization;
use Illuminate\Auth\Access\Response;

/**
 * Admin, OPD pembina, dan kelompok pemilik boleh melihat dan mengelola.
 */
class LicensePolicy
{
    use AuthorizesByOrganization;

    public function view(User $user, License $license): Response
    {
        return $this->organizationAccess($user, $license->organization_id);
    }

    public function create(User $user, Organization $organization): Response
    {
        return $this->organizationAccess($user, $organization->id);
    }

    public function update(User $user, License $license): Response
    {
        return $this->organizationAccess($user, $license->organization_id);
    }

    public function delete(User $user, License $license): Response
    {
        return $this->organizationAccess($user, $license->organization_id);
    }
}
