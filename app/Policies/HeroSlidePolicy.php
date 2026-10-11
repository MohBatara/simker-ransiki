<?php

namespace App\Policies;

use App\Models\HeroSlide;
use App\Models\User;

/** Foto hero beranda: hanya Admin. */
class HeroSlidePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, HeroSlide $slide): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, HeroSlide $slide): bool
    {
        return $user->isAdmin();
    }
}
