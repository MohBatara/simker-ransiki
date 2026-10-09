<?php

use App\Enums\Role;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\User;

test('halaman login tanpa daftar akun, lupa kata sandi via email, atau ingat saya', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Username atau email')
        ->assertSee('Hubungi Admin distrik')
        ->assertDontSee('Daftar')
        ->assertDontSee('Ingat saya');
});

test('login dengan username mengabaikan huruf besar dan spasi', function () {
    $user = User::factory()->admin()->create(['username' => 'admin.contoh']);

    $this->post('/login', ['login' => '  Admin.Contoh ', 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('login dengan email', function () {
    $user = User::factory()->admin()->create(['email' => 'admin@contoh.test']);

    $this->post('/login', ['login' => 'ADMIN@contoh.test', 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('username selalu disimpan dalam huruf kecil', function () {
    $user = User::factory()->create(['username' => ' Kelompok.Satu ']);

    expect($user->fresh()->username)->toBe('kelompok.satu');
});

test('pesan gagal sama untuk username tidak ada dan kata sandi salah', function () {
    User::factory()->admin()->create(['username' => 'admin']);

    $this->post('/login', ['login' => 'tidak.ada', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'Username/email atau kata sandi tidak cocok.']);
    $this->post('/login', ['login' => 'admin', 'password' => 'salah'])
        ->assertSessionHasErrors(['login' => 'Username/email atau kata sandi tidak cocok.']);

    $this->assertGuest();
});

test('login dikunci setelah 5 percobaan gagal, walau kata sandi berikutnya benar', function () {
    User::factory()->admin()->create(['username' => 'admin']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['login' => 'admin', 'password' => 'salah']);
    }

    $this->post('/login', ['login' => 'admin', 'password' => 'password'])
        ->assertSessionHasErrors('login');

    expect(session('errors')->first('login'))->toStartWith('Terlalu banyak percobaan masuk.');
    $this->assertGuest();
});

test('satu IP dibatasi 20 percobaan walau username berganti-ganti', function () {
    foreach (range(1, 20) as $attempt) {
        $this->post('/login', ['login' => "tebakan{$attempt}", 'password' => 'salah']);
    }

    $this->post('/login', ['login' => 'tebakan-baru', 'password' => 'salah'])
        ->assertSessionHasErrors('login');

    expect(session('errors')->first('login'))->toStartWith('Terlalu banyak percobaan masuk.');
});

test('akun nonaktif ditolak hanya setelah kata sandi benar', function () {
    User::factory()->admin()->inactive()->create(['username' => 'admin']);

    $this->post('/login', ['login' => 'admin', 'password' => 'salah'])
        ->assertSessionHasErrors(['login' => 'Username/email atau kata sandi tidak cocok.']);
    $this->post('/login', ['login' => 'admin', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'Akun ini dinonaktifkan. Hubungi Admin distrik.']);

    $this->assertGuest();
});

test('akun yang belum terhubung ke OPD atau kelompok ditolak', function (array $attributes) {
    User::factory()->create(['username' => 'belum.terhubung', ...$attributes]);

    $this->post('/login', ['login' => 'belum.terhubung', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'Akun belum terhubung ke OPD/kelompok. Hubungi Admin distrik.']);

    $this->assertGuest();
})->with([
    'OPD tanpa opd_id' => [['role' => Role::Opd, 'opd_id' => null, 'organization_id' => null]],
    'kelompok tanpa organization_id' => [['role' => Role::Kelompok, 'organization_id' => null]],
]);

test('akun yang dinonaktifkan saat sedang login langsung dikeluarkan', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user)->get('/admin')->assertOk();

    $user->update(['is_active' => false]);

    $this->get('/admin')
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['login' => 'Akun ini dinonaktifkan. Hubungi Admin distrik.']);
    $this->assertGuest();
});

test('akun yang dilepas dari OPD saat sedang login langsung dikeluarkan', function () {
    $user = User::factory()->opd()->create();
    $this->actingAs($user)->get('/opd')->assertOk();

    $user->update(['opd_id' => null]);

    $this->get('/opd')
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['login' => 'Akun belum terhubung ke OPD/kelompok. Hubungi Admin distrik.']);
});

test('login meregenerasi session', function () {
    User::factory()->admin()->create(['username' => 'admin']);
    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', ['login' => 'admin', 'password' => 'password']);

    expect(session()->getId())->not->toBe($before);
});

test('logout hanya lewat POST', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/logout')->assertMethodNotAllowed();
    $this->assertAuthenticated();

    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

test('setelah login diarahkan ke dasbor sesuai peran', function (string $state, string $dashboard) {
    $factory = User::factory();
    $factory = $state === 'opd' ? $factory->opd() : ($state === 'admin' ? $factory->admin() : $factory);
    $factory->create(['username' => 'pengguna']);

    $this->post('/login', ['login' => 'pengguna', 'password' => 'password'])->assertRedirect(route($dashboard));
})->with([
    ['admin', 'admin.dashboard'],
    ['opd', 'opd.dashboard'],
    ['kelompok', 'kelompok.dashboard'],
]);

test('halaman tujuan sebelum login dipakai bila berada di area sendiri', function () {
    $user = User::factory()->create(['username' => 'kelompok1']);
    $report = MonthlyReport::factory()->create(['organization_id' => $user->organization_id]);

    $this->get("/kelompok/laporan/{$report->id}")->assertRedirect('/login');

    $this->post('/login', ['login' => 'kelompok1', 'password' => 'password'])
        ->assertRedirect("/kelompok/laporan/{$report->id}");
});

test('halaman tujuan di area peran lain diabaikan', function () {
    User::factory()->create(['username' => 'kelompok1']);

    $this->get('/admin')->assertRedirect('/login');

    $this->post('/login', ['login' => 'kelompok1', 'password' => 'password'])
        ->assertRedirect(route('kelompok.dashboard'));
});

test('pengguna yang sudah login membuka halaman login diarahkan ke dasbornya', function () {
    $this->actingAs(User::factory()->opd(Opd::factory()->create())->create())
        ->get('/login')
        ->assertRedirect(route('opd.dashboard'));
});
