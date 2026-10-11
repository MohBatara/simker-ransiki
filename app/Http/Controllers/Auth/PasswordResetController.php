<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use App\Models\SiteSetting;
use App\Support\PublicImages;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Lupa kata sandi?" lewat password broker Laravel, hanya untuk akun aktif yang punya email. Jawaban selalu
 * sama apa pun hasilnya, dan email dikirim setelah respons selesai, agar tidak terlihat email mana yang terdaftar.
 */
class PasswordResetController extends Controller
{
    public const SENT = 'Jika email terdaftar, tautan untuk mengatur ulang kata sandi telah dikirim. Periksa juga folder spam.';

    public function create(): View
    {
        return view('auth.forgot-password', $this->cover());
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower(trim((string) $request->input('email')));

        dispatch(fn () => Password::broker()->sendResetLink(['email' => $email, 'is_active' => true]))->afterResponse();

        return back()->with('status', self::SENT);
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email'), ...$this->cover()]);
    }

    public function update(NewPasswordRequest $request): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email']]);

        $status = Password::broker()->reset(
            [
                'email' => Str::lower(trim((string) $request->input('email'))),
                'is_active' => true,
                'token' => $request->input('token'),
                'password' => $request->validated('password'),
            ],
            function ($user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'invitation_token' => null,
                    'remember_token' => Str::random(60),
                ])->save();

                // Keluarkan dari semua perangkat: kata sandi lama mungkin diketahui orang lain.
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Tautan atur ulang tidak berlaku atau sudah kedaluwarsa. Minta tautan baru.']);
        }

        return redirect()->route('login')->with('status', 'Kata sandi diperbarui. Masuk dengan kata sandi baru.');
    }

    private function cover(): array
    {
        return ['cover' => PublicImages::slot('sampul'), 'coverCredit' => SiteSetting::get('cover_credit')];
    }
}
