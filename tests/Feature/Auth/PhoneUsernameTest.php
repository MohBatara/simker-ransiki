<?php

use App\Models\Organization;
use App\Models\User;
use App\Support\Username;

test('normalisasi username: nomor HP tanpa pemisah dan +62/62 menjadi 0; username biasa huruf kecil', function () {
    expect(Username::normalize('+62 812-3456-7890'))->toBe('081234567890')
        ->and(Username::normalize('62 812.3456.7890'))->toBe('081234567890')
        ->and(Username::normalize(' 0812 3456 7890 '))->toBe('081234567890')
        ->and(Username::normalize('0812-3456-7890'))->toBe('081234567890')
        ->and(Username::normalize(' Admin.Contoh '))->toBe('admin.contoh')
        // Bukan nomor HP (ada huruf): titik dan tanda hubung tetap.
        ->and(Username::normalize('opd.62-pertanian'))->toBe('opd.62-pertanian')
        ->and(Username::normalize(null))->toBeNull();
});

test('admin membuat akun dengan username nomor HP; login dengan format +62 dan 08 berhasil', function () {
    $admin = User::factory()->admin()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($admin)->post('/admin/pengaturan/pengguna', [
        'name' => 'Ketua Nomor HP',
        'username' => '+62 812-3456-7890',
        'role' => 'kelompok',
        'organization_id' => $organization->id,
        'access' => 'temporary',
    ])->assertRedirect();

    $user = User::where('username', '081234567890')->firstOrFail();
    $user->forceFill(['password' => 'password', 'must_change_password' => false])->save();
    $this->post('/logout');

    foreach (['+62 812-3456-7890', '0812 3456 7890', '62812.3456.7890'] as $login) {
        $this->post('/login', ['login' => $login, 'password' => 'password'])->assertRedirect(route('kelompok.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
    }
});

test('username nomor HP yang sudah dipakai ditolak meski ditulis dengan format lain', function () {
    User::factory()->create(['username' => '081234567890']);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/admin/pengaturan/pengguna', ['name' => 'Ganda', 'username' => '+62 812 3456 7890', 'role' => 'admin'])
        ->assertSessionHasErrors('username');
});

test('teks bantuan username di form pengguna menyebut nomor HP', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin/pengaturan/pengguna/tambah')->assertOk()
        ->assertSee('Boleh nama singkat atau nomor HP.');
});
