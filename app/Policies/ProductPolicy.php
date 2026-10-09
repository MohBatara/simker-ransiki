<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByOrganization;
use Illuminate\Auth\Access\Response;

/**
 * Admin, OPD pembina, dan kelompok pemilik boleh melihat dan mengelola.
 */
class ProductPolicy
{
    use AuthorizesByOrganization;

    public function view(User $user, Product $product): Response
    {
        return $this->organizationAccess($user, $product->organization_id);
    }

    public function create(User $user, Organization $organization): Response
    {
        return $this->organizationAccess($user, $organization->id);
    }

    public function update(User $user, Product $product): Response
    {
        return $this->organizationAccess($user, $product->organization_id);
    }

    public function delete(User $user, Product $product): Response
    {
        return $this->organizationAccess($user, $product->organization_id);
    }
}
