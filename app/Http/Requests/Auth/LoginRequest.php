<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Percobaan per menit per (username/email + IP). */
    public const MAX_ATTEMPTS = 5;

    /** Percobaan per menit per IP, untuk menahan tebakan banyak username dari satu tempat. */
    public const MAX_ATTEMPTS_PER_IP = 20;

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $login = $this->normalizedLogin();
        $password = (string) $this->string('password');
        $user = User::where(str_contains($login, '@') ? 'email' : 'username', $login)->first();

        if (! $user) {
            // Tetap menghitung hash agar lama respons tidak membocorkan username mana yang terdaftar.
            Hash::make($password);
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->ipThrottleKey());

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        // Pesan khusus hanya ditampilkan setelah kata sandi terbukti benar.
        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => __('auth.inactive')]);
        }

        if (! $user->isLinked()) {
            throw ValidationException::withMessages(['login' => __('auth.unlinked')]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    private function ensureIsNotRateLimited(): void
    {
        foreach ([$this->throttleKey() => self::MAX_ATTEMPTS, $this->ipThrottleKey() => self::MAX_ATTEMPTS_PER_IP] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                event(new Lockout($this));

                throw ValidationException::withMessages([
                    'login' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
                ]);
            }
        }
    }

    private function normalizedLogin(): string
    {
        return Str::lower(trim((string) $this->string('login')));
    }

    private function throttleKey(): string
    {
        return 'login|'.Str::transliterate($this->normalizedLogin()).'|'.$this->ip();
    }

    private function ipThrottleKey(): string
    {
        return 'login-ip|'.$this->ip();
    }
}
