<?php

use App\Models\Organization;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->organization = Organization::factory()->create();
});

/** Buat akun lewat form Admin dengan pilihan undangan, lalu ambil tautan dari email yang terkirim. */
function inviteUser(object $test, array $overrides = []): array
{
    $test->actingAs($test->admin)->post('/admin/pengguna', [
        'name' => 'Ketua Contoh', 'username' => 'ketua.contoh', 'email' => 'ketua@contoh.test',
        'role' => 'kelompok', 'organization_id' => $test->organization->id, 'access' => 'invite', ...$overrides,
    ])->assertSessionHasNoErrors();

    $user = User::where('username', 'ketua.contoh')->sole();
    $url = null;
    Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use ($user, &$url) {
        $url = $notification->toMail($user)->viewData['url'];

        return true;
    });
    auth()->logout();

    return [$user, $url];
}

test('Admin mengirim undangan: pengguna membuat kata sandi sendiri lalu langsung masuk', function () {
    [$user, $url] = inviteUser($this);

    expect($user->hasPendingInvitation())->toBeTrue()
        ->and($user->invitation_token)->not->toBe(parse_url($url, PHP_URL_PATH)); // hanya hash yang disimpan

    $this->get($url)->assertOk()->assertSee('Buat kata sandi')->assertSee('ketua.contoh');
    $this->post($url, ['password' => 'rahasia-baru-1', 'password_confirmation' => 'rahasia-baru-1'])
        ->assertRedirect(route('kelompok.dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh())->hasPendingInvitation()->toBeFalse()->must_change_password->toBeFalse();
});

test('tautan undangan sekali pakai: setelah dipakai ditolak', function () {
    [, $url] = inviteUser($this);

    $this->post($url, ['password' => 'rahasia-baru-1', 'password_confirmation' => 'rahasia-baru-1']);
    auth()->logout();

    $this->get($url)->assertOk()->assertSee('Undangan tidak berlaku')->assertSee('sudah dipakai');
    $this->post($url, ['password' => 'coba-lagi-99', 'password_confirmation' => 'coba-lagi-99'])->assertSee('Undangan tidak berlaku');
    $this->assertGuest();
});

test('tautan undangan kedaluwarsa setelah 72 jam ditolak', function () {
    [$user, $url] = inviteUser($this);

    $this->travel(73)->hours();

    $this->get($url)->assertOk()->assertSee('sudah kedaluwarsa');
    $this->post($url, ['password' => 'rahasia-baru-1', 'password_confirmation' => 'rahasia-baru-1'])->assertSee('sudah kedaluwarsa');
    expect($user->fresh()->hasPendingInvitation())->toBeTrue();
});

test('tautan yang diubah atau diganti undangan baru ditolak', function () {
    [$user, $url] = inviteUser($this);

    $this->get($url.'x')->assertSee('tidak valid');

    $this->actingAs($this->admin)->post("/admin/pengguna/{$user->id}/undangan")->assertSessionHas('status', 'Undangan dikirim ke ketua@contoh.test.');
    auth()->logout();
    $this->get($url)->assertSee('sudah dipakai atau sudah diganti');
    Notification::assertSentToTimes($user, UserInvitation::class, 2);
});

test('pilihan undangan butuh email; tanpa undangan tetap kata sandi sementara', function () {
    $this->actingAs($this->admin)->post('/admin/pengguna', [
        'name' => 'Tanpa Email', 'username' => 'tanpa.email', 'role' => 'kelompok',
        'organization_id' => $this->organization->id, 'access' => 'invite',
    ])->assertSessionHasErrors(['email' => 'Isi email untuk mengirim undangan, atau pilih kata sandi sementara.']);

    $this->post('/admin/pengguna', [
        'name' => 'Sementara', 'username' => 'sementara', 'email' => 'sementara@contoh.test', 'role' => 'kelompok',
        'organization_id' => $this->organization->id, 'access' => 'temporary',
    ])->assertSessionHas('credentials');
    Notification::assertNothingSent();
});

test('kirim ulang undangan hanya Admin dan hanya untuk akun berpunya email', function () {
    $noEmail = User::factory()->kelompok($this->organization)->create(['email' => null]);
    $withEmail = User::factory()->kelompok($this->organization)->create(['email' => 'ada@contoh.test']);

    $this->actingAs($this->admin)->post("/admin/pengguna/{$noEmail->id}/undangan")->assertForbidden();
    $this->actingAs(User::factory()->opd()->create())->post("/admin/pengguna/{$withEmail->id}/undangan")->assertForbidden();
});

test('tanda tangan URL undangan tetap memeriksa token yang tersimpan', function () {
    $user = User::factory()->kelompok($this->organization)->create(['invitation_token' => hash('sha256', 'token-asli')]);

    $forged = URL::temporarySignedRoute('invitation.show', now()->addHour(), ['user' => $user->id, 'token' => 'token-palsu']);
    $this->get($forged)->assertSee('Undangan tidak berlaku');
});
