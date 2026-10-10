<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\PublicImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login', [
            // Foto sampul dari Konten Website untuk panel kiri; tanpa foto, panel forest polos.
            'cover' => PublicImages::slot('sampul'),
            'coverCredit' => SiteSetting::get('cover_credit'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->must_change_password) {
            return redirect()->route('account.password.edit');
        }

        return redirect()->to($this->homeFor($request, $user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Halaman yang dibuka sebelum login hanya dipakai bila berada di area peran pengguna itu.
     */
    private function homeFor(Request $request, User $user): string
    {
        $intended = $request->session()->pull('url.intended');
        $area = '/'.$user->role->area();

        if ($intended) {
            $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');

            if ($path === $area || str_starts_with($path, $area.'/')) {
                return $intended;
            }
        }

        return route($user->role->dashboardRoute());
    }
}
