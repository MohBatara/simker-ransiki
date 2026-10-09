<?php

use App\Models\User;

function userWithRole(string $role): User
{
    return match ($role) {
        'admin' => User::factory()->admin()->create(),
        'opd' => User::factory()->opd()->create(),
        'kelompok' => User::factory()->create(),
    };
}

test('setiap peran hanya bisa membuka areanya sendiri', function (string $role, string $area) {
    $response = $this->actingAs(userWithRole($role))->get("/{$area}");

    $role === $area ? $response->assertOk() : $response->assertForbidden();
})->with(['admin', 'opd', 'kelompok'])->with(['admin', 'opd', 'kelompok']);

test('area lain ditolak dengan halaman 403 berbahasa Indonesia', function () {
    $this->actingAs(userWithRole('kelompok'))
        ->get('/admin')
        ->assertForbidden()
        ->assertSee('Akses ditolak')
        ->assertSee('Halaman ini bukan untuk peran akun Anda.');
});

test('tamu diarahkan ke halaman login', function (string $area) {
    $this->get("/{$area}")->assertRedirect('/login');
})->with(['admin', 'opd', 'kelompok', 'akun/kata-sandi']);
