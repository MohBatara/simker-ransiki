<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('login pertama diarahkan ke halaman ganti kata sandi', function () {
    User::factory()->admin()->mustChangePassword()->create(['username' => 'admin']);

    $this->post('/login', ['login' => 'admin', 'password' => 'password'])
        ->assertRedirect(route('account.password.edit'));
});

test('akun wajib ganti kata sandi tidak bisa membuka halaman lain', function () {
    $this->actingAs(User::factory()->admin()->mustChangePassword()->create());

    $this->get('/admin')->assertRedirect(route('account.password.edit'));
    $this->get(route('account.password.edit'))
        ->assertOk()
        ->assertSee('kata sandi sementara')
        ->assertDontSee('Menu utama');
});

test('ganti kata sandi berhasil lalu diarahkan ke dasbor', function () {
    $user = User::factory()->admin()->mustChangePassword()->create();

    $this->actingAs($user)->put(route('account.password.update'), [
        'current_password' => 'password',
        'password' => 'rahasia-baru',
        'password_confirmation' => 'rahasia-baru',
    ])->assertRedirect(route('admin.dashboard'))->assertSessionHas('status', 'Kata sandi diperbarui.');

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('rahasia-baru', $user->password))->toBeTrue();

    $this->get('/admin')->assertOk();
});

test('ganti kata sandi ditolak dengan pesan berbahasa Indonesia', function (array $input, string $field, string $message) {
    $user = User::factory()->admin()->mustChangePassword()->create();

    $this->actingAs($user)
        ->put(route('account.password.update'), $input)
        ->assertSessionHasErrors([$field => $message]);

    expect($user->fresh()->must_change_password)->toBeTrue();
})->with([
    'kata sandi saat ini salah' => [
        ['current_password' => 'salah', 'password' => 'rahasia-baru', 'password_confirmation' => 'rahasia-baru'],
        'current_password', 'Kata sandi saat ini tidak cocok.',
    ],
    'terlalu pendek' => [
        ['current_password' => 'password', 'password' => 'pendek', 'password_confirmation' => 'pendek'],
        'password', 'Kata sandi baru paling sedikit 8 karakter.',
    ],
    'sama dengan yang lama' => [
        ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'],
        'password', 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
    ],
    'ulangi tidak cocok' => [
        ['current_password' => 'password', 'password' => 'rahasia-baru', 'password_confirmation' => 'rahasia-lain'],
        'password', 'Ulangi kata sandi tidak sama dengan kata sandi baru.',
    ],
    'kosong' => [
        [],
        'current_password', 'Kata sandi saat ini wajib diisi.',
    ],
]);

test('halaman akun saya tampil di layout app bila tidak wajib ganti', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('account.password.edit'))
        ->assertOk()
        ->assertSee('Akun Saya')
        ->assertSee('Keluar');
});
