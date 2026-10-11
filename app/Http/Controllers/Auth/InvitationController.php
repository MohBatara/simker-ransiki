<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Tautan undangan dari email: membuat kata sandi sendiri. Tanda tangan URL menjaga masa berlaku (72 jam);
 * hash token di users.invitation_token menjaga "sekali pakai" dan batal saat undangan dikirim ulang.
 */
class InvitationController extends Controller
{
    public function show(Request $request, User $user, string $token): View
    {
        if ($problem = $this->problem($request, $user, $token)) {
            return view('auth.invitation-invalid', ['message' => $problem]);
        }

        return view('auth.invitation', ['user' => $user, 'current' => $request->user()]);
    }

    public function store(NewPasswordRequest $request, User $user, string $token): RedirectResponse|View
    {
        if ($problem = $this->problem($request, $user, $token)) {
            return view('auth.invitation-invalid', ['message' => $problem]);
        }

        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
            'invitation_token' => null,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        // Browser yang sedang masuk dengan akun lain (mis. Admin yang menguji) dikeluarkan dulu.
        if (Auth::check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->role->dashboardRoute())->with('status', 'Kata sandi dibuat. Gunakan username atau email Anda untuk masuk berikutnya.');
    }

    private function problem(Request $request, User $user, string $token): ?string
    {
        if (! URL::hasCorrectSignature($request)) {
            return 'Tautan undangan tidak valid. Pastikan alamatnya disalin lengkap dari email.';
        }
        if (! URL::signatureHasNotExpired($request)) {
            return 'Tautan undangan sudah kedaluwarsa. Minta Admin distrik mengirim ulang undangan.';
        }
        if (! $user->invitation_token || ! hash_equals($user->invitation_token, hash('sha256', $token))) {
            return 'Tautan undangan sudah dipakai atau sudah diganti undangan baru. Masuk dengan kata sandi Anda, atau minta Admin distrik mengirim ulang undangan.';
        }
        if (! $user->is_active) {
            return __('auth.inactive');
        }
        if (! $user->isLinked()) {
            return __('auth.unlinked');
        }

        return null;
    }
}
