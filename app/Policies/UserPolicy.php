<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /** Kirim (ulang) undangan email: hanya untuk akun yang punya email. */
    public function invite(User $user, User $model): bool
    {
        return $user->isAdmin() && filled($model->email);
    }

    public function activate(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function deactivate(User $user, User $model): Response
    {
        return $this->manageOther($user, $model, 'Anda tidak bisa menonaktifkan akun sendiri.');
    }

    public function delete(User $user, User $model): Response
    {
        return $this->manageOther($user, $model, 'Anda tidak bisa menghapus akun sendiri.');
    }

    private function manageOther(User $user, User $model, string $selfMessage): Response
    {
        return match (true) {
            ! $user->isAdmin() => Response::deny(),
            $user->is($model) => Response::deny($selfMessage),
            default => Response::allow(),
        };
    }
}
