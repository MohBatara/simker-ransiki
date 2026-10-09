<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengeluarkan pengguna yang dinonaktifkan atau belum terhubung ke OPD/kelompok saat sedang login.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->is_active || ! $user->isLinked())) {
            $message = $user->is_active ? __('auth.unlinked') : __('auth.inactive');

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => $message]);
        }

        return $next($request);
    }
}
