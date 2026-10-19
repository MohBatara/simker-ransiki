<?php

use App\Enums\IssueKind;
use App\Enums\ProfileStatus;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;

test('x-admin.modal: dialog di tengah dengan judul, keterangan, tombol tutup, ukuran, dan konfirmasi buang perubahan', function () {
    $html = Blade::render('<x-admin.modal name="uji" title="Judul popup" description="Satu baris keterangan." size="sm">Isi<x-slot:footer><x-button>Simpan</x-button></x-slot:footer></x-admin.modal>');

    expect($html)->toContain('<dialog')
        ->toContain('x-data="adminModal(')
        ->toContain('aria-labelledby="modal-uji-title"')
        ->toContain('aria-describedby="modal-uji-desc"')
        ->toContain('aria-label="Tutup"')
        ->toContain('md:max-w-[440px]')
        ->toContain('Buang perubahan?')
        ->toContain('x-on:cancel.prevent="requestClose()"');

    expect(Blade::render('<x-admin.modal name="a" title="A">x</x-admin.modal>'))->toContain('md:max-w-[560px]')
        ->and(Blade::render('<x-admin.modal name="b" title="B" size="lg">x</x-admin.modal>'))->toContain('md:max-w-[760px]');
});

test('tidak ada lagi panel samping atau komponen modal lama', function () {
    $offenders = collect(Finder::create()->files()->in(resource_path('views'))->name('*.blade.php'))
        ->filter(fn ($file) => str_contains($file->getContents(), 'translate-x-full') || str_contains($file->getContents(), '<x-modal '))
        ->map(fn ($file) => $file->getRelativePathname())->values();

    expect($offenders->all())->toBe([])
        ->and(file_exists(resource_path('views/components/modal.blade.php')))->toBeFalse();
});

test('alamat lama form pengguna dialihkan ke daftar atau detail dengan popup terbuka', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $this->actingAs($admin);

    $this->get('/admin/pengaturan/pengguna/tambah')->assertRedirect('/admin/pengaturan/pengguna?tambah=1');
    $this->get("/admin/pengaturan/pengguna/{$user->id}/ubah")->assertRedirect("/admin/pengaturan/pengguna/{$user->id}?ubah=1");
    $this->get("/admin/pengaturan/pengguna/{$user->id}?ubah=1")->assertOk()
        ->assertSee('data-modal="user-edit" data-open-on-load', false)
        ->assertSee('Ubah akun');
});

test('ubah akun gagal validasi: popup ubah terbuka lagi dengan pesan di bawah isian', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['name' => 'Nama Lama']);

    $this->actingAs($admin)->from("/admin/pengaturan/pengguna/{$user->id}")
        ->put("/admin/pengaturan/pengguna/{$user->id}", ['name' => '', 'username' => $user->username, 'role' => 'admin', '_modal' => 'user-edit'])
        ->assertRedirect("/admin/pengaturan/pengguna/{$user->id}");

    $this->get("/admin/pengaturan/pengguna/{$user->id}")
        ->assertSee('data-modal="user-edit" data-open-on-load', false)
        ->assertSeeText('Nama wajib diisi.');
});

test('kirim ulang undangan dan atur ulang sandi lewat popup konfirmasi', function () {
    $user = User::factory()->create(['email' => 'undang@contoh.test', 'invitation_token' => hash('sha256', 'x')]);

    $this->actingAs(User::factory()->admin()->create())->get("/admin/pengaturan/pengguna/{$user->id}")->assertOk()
        ->assertSee('data-modal="send-invitation"', false)
        ->assertSee('data-modal="reset-password"', false);
});

test('tindak lanjut masalah berupa popup; dari detail laporan dibuka langsung, gagal validasi terbuka lagi', function () {
    $opd = Opd::factory()->create();
    $organization = Organization::factory()->create();
    $organization->opds()->attach($opd);
    $report = MonthlyReport::factory()->for($organization)->status(ReportStatus::Submitted)->create(['submitted_at' => now()]);
    $issue = $report->issues()->create(['kind' => IssueKind::Support, 'category' => 'pelatihan', 'description' => 'Pelatihan pengolahan.']);
    $this->actingAs(User::factory()->opd($opd)->create());

    $this->get("/opd/laporan/masalah/{$issue->id}")->assertOk()
        ->assertSee('data-modal="follow-up"', false)
        ->assertSee("\$dispatch('open-modal', 'follow-up')", false)
        ->assertDontSee('data-open-on-load', false);
    $this->get("/opd/laporan/masalah/{$issue->id}?ubah=1")->assertSee('data-modal="follow-up" data-open-on-load', false);

    $this->from("/opd/laporan/masalah/{$issue->id}")->post("/opd/laporan/masalah/{$issue->id}/tindak-lanjut", ['_modal' => 'follow-up', 'status' => 'reviewed']);
    $this->get("/opd/laporan/masalah/{$issue->id}")->assertSee('data-modal="follow-up" data-open-on-load', false);
});

test('tandai profil terverifikasi lewat popup konfirmasi di kepala detail dan di antrean', function () {
    $opd = Opd::factory()->create();
    $organization = Organization::factory()->create(['profile_status' => ProfileStatus::Unverified]);
    $organization->opds()->attach($opd);
    $this->actingAs(User::factory()->opd($opd)->create());

    $this->get("/opd/kelompok/{$organization->id}")->assertOk()
        ->assertSee("\$dispatch('open-modal', 'verify-profile')", false)
        ->assertSee('data-modal="verify-profile"', false);
    $this->get('/opd/kelompok/verifikasi-profil?jenis=baru')->assertOk()
        ->assertSee('data-modal="verify-profile"', false)
        ->assertSee('x-bind:action="verifying.url"', false)
        ->assertSee('Tandai terverifikasi');
});
