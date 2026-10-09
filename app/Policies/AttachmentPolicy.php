<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Hak atas lampiran mengikuti hak atas induknya (kelompok, izin, produk, atau laporan).
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): Response
    {
        return Gate::forUser($user)->inspect('view', $attachment->loadMissing('attachable')->attachable);
    }

    /** Menghapus lampiran dianggap mengubah induknya. */
    public function delete(User $user, Attachment $attachment): Response
    {
        return Gate::forUser($user)->inspect('update', $attachment->loadMissing('attachable')->attachable);
    }
}
