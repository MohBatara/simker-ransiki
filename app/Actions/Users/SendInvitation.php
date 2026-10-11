<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Undangan akun lewat email: tautan bertanda tangan (berlaku INVITATION_HOURS jam) untuk membuat kata sandi
 * sendiri. Hanya hash token yang disimpan; token dikosongkan setelah dipakai atau saat Admin mereset kata sandi,
 * dan setiap kirim ulang membatalkan tautan sebelumnya.
 */
class SendInvitation
{
    public const INVITATION_HOURS = 72;

    public function handle(User $user): void
    {
        $token = Str::random(64);

        $user->forceFill([
            // Kata sandi acak yang tidak diketahui siapa pun sampai undangan diterima.
            'password' => Str::random(40),
            'must_change_password' => false,
            'invitation_token' => hash('sha256', $token),
            'invited_at' => now(),
        ])->save();

        $expires = now()->addHours(self::INVITATION_HOURS);

        $user->notify(new UserInvitation(
            URL::temporarySignedRoute('invitation.show', $expires, ['user' => $user->id, 'token' => $token]),
            $expires,
        ));
    }
}
