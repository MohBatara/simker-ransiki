<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->kelompok(Organization::factory()->create())->create(['email' => 'ketua@contoh.test', 'must_change_password' => false]);
});

test('pesan selalu sama untuk email terdaftar dan tidak terdaftar', function () {
    $registered = $this->from('/lupa-kata-sandi')->post('/lupa-kata-sandi', ['email' => 'Ketua@Contoh.test ']);
    $unknown = $this->from('/lupa-kata-sandi')->post('/lupa-kata-sandi', ['email' => 'tidak-ada@contoh.test']);

    foreach ([$registered, $unknown] as $response) {
        $response->assertRedirect('/lupa-kata-sandi')->assertSessionHas('status', PasswordResetController::SENT)->assertSessionHasNoErrors();
    }
    Notification::assertSentToTimes($this->user, ResetPassword::class, 1);
    Notification::assertCount(1);
});

test('akun nonaktif tidak dikirimi tautan, tetapi pesannya tetap sama', function () {
    $this->user->update(['is_active' => false]);

    $this->post('/lupa-kata-sandi', ['email' => 'ketua@contoh.test'])->assertSessionHas('status', PasswordResetController::SENT);
    Notification::assertNothingSent();
});

test('tautan atur ulang mengganti kata sandi, lalu tidak bisa dipakai lagi', function () {
    $this->post('/lupa-kata-sandi', ['email' => 'ketua@contoh.test']);

    $url = null;
    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification) use (&$url) {
        $url = $notification->toMail($this->user)->viewData['url'];

        return true;
    });
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $token = basename(parse_url($url, PHP_URL_PATH));

    $this->get($url)->assertOk()->assertSee('Atur ulang kata sandi');
    $payload = ['token' => $token, 'email' => $query['email'], 'password' => 'kata-sandi-baru', 'password_confirmation' => 'kata-sandi-baru'];
    $this->post('/atur-ulang-kata-sandi', $payload)->assertRedirect('/login')->assertSessionHas('status');
    expect(Hash::check('kata-sandi-baru', $this->user->fresh()->password))->toBeTrue();

    $this->from($url)->post('/atur-ulang-kata-sandi', [...$payload, 'password' => 'lain-lagi-123', 'password_confirmation' => 'lain-lagi-123'])
        ->assertSessionHasErrors(['email' => 'Tautan atur ulang tidak berlaku atau sudah kedaluwarsa. Minta tautan baru.']);
});

test('token salah ditolak dengan pesan yang sama untuk email apa pun', function () {
    foreach (['ketua@contoh.test', 'tidak-ada@contoh.test'] as $email) {
        $this->post('/atur-ulang-kata-sandi', ['token' => 'palsu', 'email' => $email, 'password' => 'kata-sandi-baru', 'password_confirmation' => 'kata-sandi-baru'])
            ->assertSessionHasErrors(['email' => 'Tautan atur ulang tidak berlaku atau sudah kedaluwarsa. Minta tautan baru.']);
    }
});

test('permintaan tautan dibatasi (throttle)', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post('/lupa-kata-sandi', ['email' => 'siapa@contoh.test'])->assertRedirect();
    }

    $this->post('/lupa-kata-sandi', ['email' => 'siapa@contoh.test'])->assertTooManyRequests();
});

test('halaman lupa kata sandi menjelaskan jalur untuk akun tanpa email', function () {
    $this->get('/lupa-kata-sandi')->assertOk()->assertSee('Hubungi Admin distrik untuk mengatur ulang');
});
