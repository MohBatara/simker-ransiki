<?php

use App\Enums\Role;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Js;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Admin Uji', 'username' => 'admin']);
    $this->opd = Opd::factory()->create(['name' => 'Dinas Contoh', 'short_name' => 'Contoh']);
    $this->organization = Organization::factory()->create(['name' => 'KUB Contoh Akun']);
});

/** Kata sandi sementara yang ditampilkan sekali di halaman detail setelah redirect. */
function temporaryPasswordFrom($response): string
{
    return Crypt::decryptString($response->getSession()->get('credentials')['password']);
}

test('hanya admin yang bisa mengelola pengguna', function (string $role) {
    $target = User::factory()->create();
    $this->actingAs($role === 'opd' ? User::factory()->opd($this->opd)->create() : User::factory()->create());

    $this->get('/admin/pengaturan/pengguna')->assertForbidden();
    $this->get('/admin/pengaturan/pengguna/tambah')->assertForbidden();
    $this->post('/admin/pengaturan/pengguna', ['name' => 'X', 'username' => 'x.baru', 'role' => 'admin'])->assertForbidden();
    $this->post("/admin/pengaturan/pengguna/{$target->id}/reset-kata-sandi")->assertForbidden();
    $this->patch("/admin/pengaturan/pengguna/{$target->id}/nonaktifkan")->assertForbidden();

    expect(User::where('username', 'x.baru')->exists())->toBeFalse()
        ->and($target->fresh()->is_active)->toBeTrue();
})->with(['opd', 'kelompok']);

test('daftar pengguna bisa difilter peran, status, dan kata kunci', function (string $query, array $visible) {
    User::factory()->opd($this->opd)->create(['name' => 'Petugas Contoh Aktif']);
    User::factory()->opd($this->opd)->inactive()->create(['name' => 'Petugas Contoh Nonaktif']);
    User::factory()->kelompok($this->organization)->create(['name' => 'Ketua Contoh Akun']);

    $response = $this->actingAs($this->admin)->get('/admin/pengaturan/pengguna?'.$query)->assertOk();

    foreach (['Petugas Contoh Aktif', 'Petugas Contoh Nonaktif', 'Ketua Contoh Akun'] as $name) {
        in_array($name, $visible) ? $response->assertSee($name) : $response->assertDontSee($name);
    }
})->with([
    'semua' => ['', ['Petugas Contoh Aktif', 'Petugas Contoh Nonaktif', 'Ketua Contoh Akun']],
    'peran OPD' => ['role=opd', ['Petugas Contoh Aktif', 'Petugas Contoh Nonaktif']],
    'peran kelompok' => ['role=kelompok', ['Ketua Contoh Akun']],
    'nonaktif' => ['status=inactive', ['Petugas Contoh Nonaktif']],
    'gabungan' => ['role=opd&status=active', ['Petugas Contoh Aktif']],
    'kata kunci' => ['q=ketua', ['Ketua Contoh Akun']],
]);

test('akun baru mendapat kata sandi sementara yang ditampilkan sekali', function () {
    $this->actingAs($this->admin);

    $response = $this->post('/admin/pengaturan/pengguna', [
        'name' => 'Petugas Baru', 'username' => '  Petugas.Baru ', 'role' => 'opd', 'opd_id' => $this->opd->id,
    ]);

    $user = User::where('username', 'petugas.baru')->firstOrFail();
    $password = temporaryPasswordFrom($response);
    $response->assertRedirect("/admin/pengaturan/pengguna/{$user->id}");

    expect($user->role)->toBe(Role::Opd)
        ->and($user->opd_id)->toBe($this->opd->id)
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check($password, $user->password))->toBeTrue()
        // Hanya hash yang disimpan, dan flash session tidak berisi kata sandi asli.
        ->and($user->password)->not->toBe($password)
        ->and(json_encode(session()->all()))->not->toContain($password);

    $this->get("/admin/pengaturan/pengguna/{$user->id}")
        ->assertSee($password)
        ->assertSee('Salin info masuk')
        // Teks yang disalin tombol "Salin info masuk".
        ->assertSee(Js::from('Alamat: '.route('login').', Username: petugas.baru, Kata sandi sementara: '.$password)->toHtml(), false);

    $this->get("/admin/pengaturan/pengguna/{$user->id}")->assertDontSee($password);
});

test('pengguna masuk dengan kata sandi sementara lalu wajib menggantinya', function () {
    $response = $this->actingAs($this->admin)->post('/admin/pengaturan/pengguna', [
        'name' => 'Ketua Baru', 'username' => 'ketua.baru', 'role' => 'kelompok', 'organization_id' => $this->organization->id,
    ]);
    $password = temporaryPasswordFrom($response);

    $this->post('/logout');
    $this->post('/login', ['login' => 'ketua.baru', 'password' => $password])
        ->assertRedirect(route('account.password.edit'));
});

test('akun OPD wajib memilih OPD dan akun kelompok wajib memilih kelompok', function (array $input, string $field, string $message) {
    $this->actingAs($this->admin)
        ->post('/admin/pengaturan/pengguna', ['name' => 'Akun Uji', 'username' => 'akun.uji', ...$input])
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'OPD tanpa OPD' => [['role' => 'opd'], 'opd_id', 'Pilih OPD untuk akun OPD.'],
    'kelompok tanpa kelompok' => [['role' => 'kelompok'], 'organization_id', 'Pilih kelompok untuk akun kelompok.'],
    'tanpa peran' => [[], 'role', 'Peran wajib diisi.'],
]);

test('OPD dan kelompok hanya disimpan sesuai peran', function () {
    $this->actingAs($this->admin)->post('/admin/pengaturan/pengguna', [
        'name' => 'Ketua Baru', 'username' => 'ketua.baru', 'role' => 'kelompok',
        'organization_id' => $this->organization->id, 'opd_id' => $this->opd->id,
    ]);

    $user = User::where('username', 'ketua.baru')->firstOrFail();
    expect($user->organization_id)->toBe($this->organization->id)->and($user->opd_id)->toBeNull();
});

test('username unik tanpa membedakan huruf besar dan hanya memakai karakter yang diizinkan', function (string $username, string $message) {
    User::factory()->create(['username' => 'ketua.satu']);

    $this->actingAs($this->admin)
        ->post('/admin/pengaturan/pengguna', ['name' => 'Akun Uji', 'username' => $username, 'role' => 'admin'])
        ->assertSessionHasErrors(['username' => $message]);
})->with([
    'kembar' => ['Ketua.Satu', 'Username sudah dipakai.'],
    'ada spasi' => ['ketua satu', 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.'],
    'ada @' => ['ketua@desa', 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.'],
]);

test('form tambah dari detail kelompok sudah memilih kelompok itu', function () {
    $this->actingAs($this->admin);

    $this->get("/admin/kelompok/{$this->organization->id}/akun")
        ->assertSee('Tambah akun')
        ->assertSee("/admin/pengaturan/pengguna/tambah?kelompok={$this->organization->id}", false);

    $this->get("/admin/pengaturan/pengguna/tambah?kelompok={$this->organization->id}")
        ->assertOk()
        ->assertSee('<option value="kelompok" selected>', false)
        ->assertSee('<option value="'.$this->organization->id.'" selected>', false);
});

test('reset kata sandi membuat kata sandi sementara baru dan mengeluarkan pengguna dari semua perangkat', function () {
    $user = User::factory()->create(['username' => 'ketua.lupa']);
    DB::table('sessions')->insert(['id' => 'sesi-lama', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    config(['session.driver' => 'database']);

    $response = $this->actingAs($this->admin)->post("/admin/pengaturan/pengguna/{$user->id}/reset-kata-sandi");
    $password = temporaryPasswordFrom($response);

    $response->assertRedirect("/admin/pengaturan/pengguna/{$user->id}")->assertSessionHas('status', 'Kata sandi direset.');
    $user->refresh();
    expect($user->must_change_password)->toBeTrue()
        ->and(Hash::check($password, $user->password))->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});

test('admin menonaktifkan dan mengaktifkan kembali akun', function () {
    $user = User::factory()->create(['username' => 'ketua.aktif']);
    $this->actingAs($this->admin);

    $this->patch("/admin/pengaturan/pengguna/{$user->id}/nonaktifkan")->assertSessionHas('status', 'Akun dinonaktifkan.');
    expect($user->fresh()->is_active)->toBeFalse();

    $this->post('/logout');
    $this->post('/login', ['login' => 'ketua.aktif', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'Akun ini dinonaktifkan. Hubungi Admin distrik.']);

    $this->actingAs($this->admin)->patch("/admin/pengaturan/pengguna/{$user->id}/aktifkan")->assertSessionHas('status', 'Akun diaktifkan.');
    expect($user->fresh()->is_active)->toBeTrue();
});

test('admin tidak bisa menonaktifkan akunnya sendiri', function () {
    $this->actingAs($this->admin);

    $this->get("/admin/pengaturan/pengguna/{$this->admin->id}")
        ->assertSee('Anda tidak bisa menonaktifkan akun sendiri.')
        ->assertDontSee('Nonaktifkan akun');

    $this->patch("/admin/pengaturan/pengguna/{$this->admin->id}/nonaktifkan")
        ->assertForbidden()
        ->assertSee('Anda tidak bisa menonaktifkan akun sendiri.');

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

test('admin tidak bisa mengubah peran akunnya sendiri', function () {
    $this->actingAs($this->admin)
        ->put("/admin/pengaturan/pengguna/{$this->admin->id}", ['name' => 'Admin Baru', 'username' => 'admin', 'role' => 'kelompok', 'organization_id' => $this->organization->id])
        ->assertSessionHasNoErrors();

    $this->admin->refresh();
    expect($this->admin->name)->toBe('Admin Baru')
        ->and($this->admin->role)->toBe(Role::Admin)
        ->and($this->admin->organization_id)->toBeNull();
});

test('admin mengubah peran akun lain dan relasinya ikut menyesuaikan', function () {
    $user = User::factory()->kelompok($this->organization)->create(['username' => 'pindah.peran']);

    $this->actingAs($this->admin)
        ->put("/admin/pengaturan/pengguna/{$user->id}", ['name' => $user->name, 'username' => 'pindah.peran', 'role' => 'opd', 'opd_id' => $this->opd->id])
        ->assertSessionHas('status', 'Akun diperbarui.');

    $user->refresh();
    expect($user->role)->toBe(Role::Opd)->and($user->opd_id)->toBe($this->opd->id)->and($user->organization_id)->toBeNull();
});

test('ID pengguna yang tidak ada 404', function () {
    $this->actingAs($this->admin)->get('/admin/pengaturan/pengguna/999999')->assertNotFound();
});
