<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Support\Facades\DB;

/**
 * Membuat kata sandi sementara untuk akun baru atau reset oleh Admin.
 * Mengembalikan kata sandi asli; hanya hash-nya yang disimpan.
 */
class IssueTemporaryPassword
{
    public function handle(User $user): string
    {
        $password = TemporaryPassword::generate();

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
            // Tautan undangan yang belum dipakai ikut batal.
            'invitation_token' => null,
        ])->save();

        // Keluarkan pengguna dari semua perangkat agar kata sandi lama tidak bisa dipakai lagi.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        return $password;
    }
}
