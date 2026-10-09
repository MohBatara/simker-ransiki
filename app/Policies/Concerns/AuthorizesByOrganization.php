<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Auth\Access\Response;

trait AuthorizesByOrganization
{
    /**
     * Data kelompok lain dijawab 404 agar keberadaan ID-nya tidak terbongkar.
     */
    protected function organizationAccess(User $user, int $organizationId): Response
    {
        return $user->canAccessOrganization($organizationId)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
