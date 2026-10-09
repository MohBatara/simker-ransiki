<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        // Saat wajib ganti, tampil tanpa menu agar pengguna tidak bisa ke halaman lain dulu.
        return view($request->user()->must_change_password ? 'account.password-required' : 'account.password');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect()->route($user->role->dashboardRoute())->with('status', 'Kata sandi diperbarui.');
    }
}
