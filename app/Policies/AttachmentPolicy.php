<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Models\Attachment;
use App\Models\MonthlyReport;
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
        $parent = $attachment->loadMissing('attachable')->attachable;

        // Lampiran laporan terverifikasi adalah bukti resmi; tidak boleh dihapus siapa pun (CLAUDE.md Bagian 5).
        if ($parent instanceof MonthlyReport && $parent->status === ReportStatus::Verified) {
            return Response::deny('Lampiran laporan yang sudah terverifikasi tidak bisa dihapus.');
        }

        return Gate::forUser($user)->inspect('update', $parent);
    }
}
